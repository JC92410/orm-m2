<?php
/**
 * Ce fichier est développé pour la gestion de la librairie Mélanie2
 * Cette Librairie permet d'accèder aux données sans avoir à implémenter de couche SQL
 * Des objets génériques vont permettre d'accèder et de mettre à jour les données
 *
 * ORM M2 Copyright © 2017  PNE Annuaire et Messagerie/MEDDE
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace LibMelanie\Objects;

use LibMelanie\Lib\MagicObject;
use LibMelanie\Interfaces\IObjectMelanie;
use LibMelanie\Sql;
use LibMelanie\Config\ConfigSQL;
use LibMelanie\Config\MappingMce;
use LibMelanie\Log\M2Log;

/**
 * Traitement des pièces jointes Melanie2
 * @author PNE Messagerie/Apitech
 * @package Librairie Mélanie2
 * @subpackage ORM
 *
 */
class AttachmentMelanie extends MagicObject implements IObjectMelanie {
  /**
	 * constructeur par défaut, appelé par PDO
	 */
	function __construct() {
	    // Défini la classe courante
	    $this->get_class = get_class($this);

		M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class."->__construct()");
    	// Initialisation du backend SQL
		Sql\DBMelanie::Initialize(ConfigSQL::$CURRENT_BACKEND);

		// Récupération du type d'objet en fonction de la class
		$this->objectType = explode('\\',$this->get_class);
		$this->objectType = $this->objectType[count($this->objectType)-1];

		if (isset(MappingMce::$Primary_Keys[$this->objectType])) {
			if (is_array(MappingMce::$Primary_Keys[$this->objectType])) $this->primaryKeys = MappingMce::$Primary_Keys[$this->objectType];
			else $this->primaryKeys = [MappingMce::$Primary_Keys[$this->objectType]];
		}
	}

	/**
	 * charger l'objet
	 */
	function load() {
		M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class."->load()");
		// Initialisation du backend SQL
	    Sql\DBMelanie::Initialize(ConfigSQL::$CURRENT_BACKEND);
		// Si les clés primaires ne sont pas définis, impossible de charger l'objet
		if (!isset($this->primaryKeys)) return false;
		// Test si l'objet existe, pas besoin de load
		if (is_bool($this->isExist) && $this->isLoaded) {
		  return $this->isExist;
		}
		// Paramètres de la requête
		$params = [];
		$whereClause = "";
		// Test si les clés primaires sont bien instanciées et les ajoute en paramètres
		foreach ($this->primaryKeys as $key) {
			if (!isset($this->$key)) return false;
			// Récupèration des données de mapping
			if (isset(MappingMce::$Data_Mapping[$this->objectType])
					&& isset(MappingMce::$Data_Mapping[$this->objectType][$key])) {
				$mapKey = MappingMce::$Data_Mapping[$this->objectType][$key][MappingMce::name];
			} else {
				$mapKey = $key;
			}
			$params[$mapKey] = $this->$key;
			if ($whereClause != "") $whereClause .= " AND ";
			$whereClause .= "$mapKey = :$mapKey";
		}
		// Chargement de la requête
		$query = Sql\SqlAttachmentRequests::getAttachmentData;
		// Clause where
		$query = str_replace("{where_clause}", $whereClause, $query);

		// Liste les attachments
		$this->isExist = Sql\DBMelanie::ExecuteQueryToObject($query, $params, $this);
		if ($this->isExist) {
		  $this->initializeHasChanged();
		}
		// Les données sont chargées
		$this->isLoaded = true;
		return $this->isExist;
	}

	/**
	 * Sauvegarder l'objet
	 * @return boolean True si c'est une command Insert, False si c'est un Update
	 */
	function save() {
		M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class."->save()");
		// Initialisation du backend SQL
	    Sql\DBMelanie::Initialize(ConfigSQL::$CURRENT_BACKEND);
		$insert = false;
		// Si les clés primaires ne sont pas définis, impossible de charger l'objet
		if (!isset($this->primaryKeys)) return null;

		// Ne rien sauvegarder si rien n'a changé
		$haschanged = false;
		foreach ($this->haschanged as $value) {
			$haschanged = $haschanged || $value;
			if ($haschanged) break;
		}
		if (!$haschanged) return null;
		// Si isExist est à null c'est qu'on n'a pas encore testé
		if (!is_bool($this->isExist)) {
		  $this->isExist = $this->exists();
		}
		// Si l'objet existe on fait un UPDATE
		if ($this->isExist) {
			// Paramètres de la requête
			$params = [];
			$whereClause = "";
			// Test si les clés primaires sont bien instanciées et les ajoute en paramètres
			foreach ($this->primaryKeys as $key) {
				if (!isset($this->$key)) return null;
				// Récupèration des données de mapping
				if (isset(MappingMce::$Data_Mapping[$this->objectType])
						&& isset(MappingMce::$Data_Mapping[$this->objectType][$key])) {
					$mapKey = MappingMce::$Data_Mapping[$this->objectType][$key][MappingMce::name];
				} else {
					$mapKey = $key;
				}
				$params[$mapKey] = $this->$key;
				if ($whereClause != "") $whereClause .= " AND ";
				$whereClause .= "$mapKey = :$mapKey";
			}

			// Liste les modification à faire
			$update = "";
			foreach ($this->haschanged as $key => $value) {
				if ($value && !isset($params[$key])) {
					if ($update != "") $update .= ", ";
					$update .= "$key = :$key";
					$params[$key] = $this->$key;
				}
			}
			// Pas d'update
			if ($update == "") return null;

			// Replace
			$query = Sql\SqlAttachmentRequests::updateAttachment;
			$query = str_replace("{attachment_set}", $update, $query);
			$query = str_replace("{where_clause}", $whereClause, $query);

			// Execute
			$this->isExist = Sql\DBMelanie::ExecuteQuery($query, $params);
		} else {
			// C'est une Insertion
			$insert = true;
			// Test si les clés primaires sont bien instanciées
			foreach ($this->primaryKeys as $key) {
				if (!isset($this->$key)) return null;
			}

			// Gestion de history_id
			if (!isset($this->id)) Sql\DBMelanie::ExecuteQueryToObject(Sql\SqlAttachmentRequests::getNextAttachment, null, $this);

			// Si l'objet n'existe pas, on fait un INSERT
			// Liste les insertion à faire
			$data_fields = "";
			$data_values = "";
			$params = [];
			foreach ($this->haschanged as $key => $value) {
				if ($value) {
					if ($data_fields != "") $data_fields .= ", ";
					if ($data_values != "") $data_values .= ", ";
					$data_fields .= $key;
					$data_values .= ":".$key;
					$params[$key] = $this->$key;
				}
			}
			// Pas d'insert
			if ($data_fields == "") return null;

			// Replace
			$query = str_replace("{data_fields}", $data_fields, Sql\SqlAttachmentRequests::insertAttachment);
			$query = str_replace("{data_values}", $data_values, $query);

			// Execute
			$this->isExist = Sql\DBMelanie::ExecuteQuery($query, $params);
		}
		if ($this->isExist) $this->initializeHasChanged();
		return $insert;
	}

	/**
	 * Suppression de l'objet
	 * @return boolean
	 */
	function delete() {
		M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class."->delete()");
		// Initialisation du backend SQL
	    Sql\DBMelanie::Initialize(ConfigSQL::$CURRENT_BACKEND);
		// Si les clés primaires ne sont pas définies, impossible de supprimer l'objet
		if (!isset($this->primaryKeys)) return false;
		// Paramètres de la requête
		$params = [];
		$whereClause = "";
		// Test si les clés primaires sont bien instanciées et les ajoute en paramètres
		foreach ($this->primaryKeys as $key) {
			if (!isset($key)) return false;
			// Récupèration des données de mapping
			if (isset(MappingMce::$Data_Mapping[$this->objectType])
					&& isset(MappingMce::$Data_Mapping[$this->objectType][$key])) {
				$mapKey = MappingMce::$Data_Mapping[$this->objectType][$key][MappingMce::name];
			} else {
				$mapKey = $key;
			}
			$params[$mapKey] = $this->$key;
			if ($whereClause != "") $whereClause .= " AND ";
			$whereClause .= "$mapKey = :$mapKey";
		}

		$query = Sql\SqlAttachmentRequests::deleteAttachment;
		// Clause where
		$query = str_replace("{where_clause}", $whereClause, $query);

		// Supprimer l'évènement
		$ret = Sql\DBMelanie::ExecuteQuery($query, $params);
		if ($ret) {
		  $this->initializeHasChanged();
		  $this->isExist = false;
		}
		return $ret;
	}

	/**
	 * Est-ce que l'objet existe déjà
	 */
	function exists() {
		M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class."->exists()");
		// Initialisation du backend SQL
	    Sql\DBMelanie::Initialize(ConfigSQL::$CURRENT_BACKEND);
		// Si les clés primaires ne sont pas définis, impossible de charger l'objet
		if (!isset($this->primaryKeys)) return false;
		// Test si l'objet existe, pas besoin de load
		if (is_bool($this->isExist)) {
		  return $this->isExist;
		}
		// Paramètres de la requête
		$params = [];
		$whereClause = "";
		// Test si les clés primaires sont bien instanciées et les ajoute en paramètres
		foreach ($this->primaryKeys as $key) {
			if (!isset($this->$key)) return false;
			// Récupèration des données de mapping
			if (isset(MappingMce::$Data_Mapping[$this->objectType])
					&& isset(MappingMce::$Data_Mapping[$this->objectType][$key])) {
				$mapKey = MappingMce::$Data_Mapping[$this->objectType][$key][MappingMce::name];
			} else {
				$mapKey = $key;
			}
			$params[$mapKey] = $this->$key;
			if ($whereClause != "") $whereClause .= " AND ";
			$whereClause .= "$mapKey = :$mapKey";
		}

		$query = Sql\SqlAttachmentRequests::getAttachmentsList;
		// Clause where
		$query = str_replace("{where_clause}", $whereClause, $query);
		$query = str_replace("{fields_list}", "vfs_name", $query);

		// Liste les objets
		$res = Sql\DBMelanie::ExecuteQuery($query, $params);
		$this->isExist = (count($res) >= 1);
		return $this->isExist;
	}

	/**
	 * Permet de récupérer la liste d'objet en utilisant les données passées
	 * (la clause where s'adapte aux données)
	 * Il faut donc peut être sauvegarder l'objet avant d'appeler cette méthode
	 * pour réinitialiser les données modifiées (propriété haschanged)
	 * @param String[] $fields Liste les champs à récupérer depuis les données
	 * @param String $filter Filtre pour la lecture des données en fonction des valeurs déjà passé, exemple de filtre : "((#description# OR #title#) AND #start#)"
	 * @param String[] $operators Liste les propriétés par operateur (MappingMce::like, MappingMce::supp, MappingMce::inf, MappingMce::diff)
	 * @param String $orderby Tri par le champ
	 * @param bool $asc Tri ascendant ou non
	 * @param int $limit Limite le nombre de résultat (utile pour la pagination)
	 * @param int $offset Offset de début pour les résultats (utile pour la pagination)
	 * @param String[] $case_unsensitive_fields Liste des champs pour lesquels on ne sera pas sensible à la casse
	 * @return ObjectMelanie[] Array
	 */
	function getList($fields = [], $filter = "", $operators = [], $orderby = "", $asc = true, $limit = null, $offset = null, $case_unsensitive_fields = []) {
		M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class."->getList()");
		// Initialisation du backend SQL
	    Sql\DBMelanie::Initialize(ConfigSQL::$CURRENT_BACKEND);
		// Mapping pour les operateurs
		$opmapping = [];
		// Test si les clés primaires sont bien instanciées et les ajoute en paramètres
		foreach ($operators as $key => $operator) {
			// Récupèration des données de mapping
			if (isset(MappingMce::$Data_Mapping[$this->objectType])
					&& isset(MappingMce::$Data_Mapping[$this->objectType][$key])) {
				$key = MappingMce::$Data_Mapping[$this->objectType][$key][MappingMce::name];
			}
			$opmapping[$key] = $operator;
		}
		// Mapping pour les champs
		$fieldsmapping = [];
		if (is_array($fields)) {
    		// Test si les clés primaires sont bien instanciées et les ajoute en paramètres
    		foreach ($fields as $key) {
    			// Récupèration des données de mapping
    			if (isset(MappingMce::$Data_Mapping[$this->objectType])
    					&& isset(MappingMce::$Data_Mapping[$this->objectType][$key])) {
    				$key = MappingMce::$Data_Mapping[$this->objectType][$key][MappingMce::name];
    			}
    			$fieldsmapping[] = $key;
    		}
		}
		// Paramètres de la requête
		$whereClause = "";
		$params = [];
		// Est-ce qu'un filtre est activé
		if ($filter != "") {
			// Recherche toutes les entrées du filtre
			// TODO: Attention la regex ne prend que a-z ce qui correspond au mapping actuel
			preg_match_all("/#([a-z0-9]*)#/",
			    strtolower($filter),
			    $matches, PREG_PATTERN_ORDER);
			if (isset($matches[1])) {
				foreach ($matches[1] as $key) {
				    // Est-ce que le champ courant est non case sensitive
				    $is_case_unsensitive = in_array($key, $case_unsensitive_fields);
					// Récupèration des données de mapping
					if (isset(MappingMce::$Data_Mapping[$this->objectType])
							&& isset(MappingMce::$Data_Mapping[$this->objectType][$key])) {
						$mapKey = MappingMce::$Data_Mapping[$this->objectType][$key][MappingMce::name];
					} else {
						$mapKey = $key;
					}
					if (isset($opmapping[$mapKey])) {
						if (is_array($this->$mapKey)) {
						    if ($opmapping[$mapKey] == MappingMce::in 
						          || $opmapping[$mapKey] == MappingMce::notin) {
						        // Filtre personnalisé, valeur multiple, pas de like, on utilise IN
						        if ($is_case_unsensitive)
						            $clause = "LOWER($mapKey) " . $opmapping[$mapKey] . " (";
						        else
						            $clause = "$mapKey " . $opmapping[$mapKey] . " (";
						        $i = 1;
						        foreach ($this->$mapKey as $val) {
						            if ($i > 1) $clause .= ", ";
						            $clause .= ":$mapKey$i";
						            if ($is_case_unsensitive)
						                $params[$mapKey.$i] = strtolower($val);
						            else
						                $params[$mapKey.$i] = $val;
						            $i++;
						        }
						        $clause .= ")";
						        $filter = str_replace("#$key#", $clause, $filter);
						    } else if ($opmapping[$mapKey] == MappingMce::between
						        || $opmapping[$mapKey] == MappingMce::notbetween) {
				          $value = $this->$mapKey;
				          // Filtre personnalisé, avec between
				          if ($is_case_unsensitive) {
				            $clause = "(LOWER($mapKey) " . $opmapping[$mapKey] . " :" . $mapKey . "0 AND :" . $mapKey . "1)";
				            $params[$mapKey."0"] = strtolower($value[0]);
				            $params[$mapKey."1"] = strtolower($value[1]);
				          }
				          else {
				            $clause = "($mapKey " . $opmapping[$mapKey] . " :" . $mapKey . "0 AND :" . $mapKey . "1)";
				            $params[$mapKey."0"] = $value[0];
				            $params[$mapKey."1"] = $value[1];
				          }
				          $filter = str_replace("#$key#", $clause, $filter);
						    } else {
    							// Filtre personnalisé, valeur multiple, avec like
    							$clause = "(";
    							$i = 1;
    							foreach ($this->$mapKey as $val) {
    								if ($i > 1) {
    									if ($opmapping[$mapKey] == MappingMce::diff) $clause .= " AND ";
    									else $clause .= " OR ";
    								}
    								if ($is_case_unsensitive) {
    								    $clause .= "LOWER($mapKey) " . $opmapping[$mapKey] . " ";
    								    $clause .= ":$mapKey$i";
    								    $params[$mapKey.$i] = strtolower($val);
    								} else {
    								    $clause .= "$mapKey " . $opmapping[$mapKey] . " ";
    								    $clause .= ":$mapKey$i";
    								    $params[$mapKey.$i] = $val;
    								}
    								$i++;
    							}
    							$clause .= ")";
    							$filter = str_replace("#$key#", $clause, $filter);
						    }
						} else {
							// Filtre personnalisé, valeur simple avec LIKE
						    if ($is_case_unsensitive) {
						        $clause = "LOWER($mapKey) " . $opmapping[$mapKey] . " :$mapKey";
						        $params[$mapKey] = strtolower($this->$mapKey);
						    } else {
						        $clause = "$mapKey " . $opmapping[$mapKey] . " :$mapKey";
						        $params[$mapKey] = $this->$mapKey;
						    }
							$filter = str_replace("#$key#", $clause, $filter);
						}
					} else {
						// Filtre personnalise, on ne met que le nom du champ
					    if ($is_case_unsensitive)
					        $clause = "LOWER($mapKey)";
					    else
					        $clause = "$mapKey";
					    $filter = str_replace("#$key#", $clause, $filter);
					}
				}
			}
			$whereClause = $filter;
		} else {
			// Gestion du where clause en fonction du haschanged
			// N'ajoute que les paramètres qui ont changé
			foreach ($this->haschanged as $key => $value) {
				if ($value) {
				    // Est-ce que le champ courant est non case sensitive
				    $is_case_unsensitive = in_array($key, $case_unsensitive_fields);
					if (isset($opmapping[$key])) {
						if (is_array($this->$key)) {
							// On est dans un tableau et il nous faut utiliser LIKE
							if ($whereClause != "") $whereClause .= " AND ";
							$i = 1;
							foreach ($this->$key as $val) {
								if ($i > 1) {
									if ($opmapping[$key] == MappingMce::diff) $whereClause .= " AND ";
									else $whereClause .= " OR ";
								} else $whereClause .= "(";
								if ($is_case_unsensitive) {
    								$whereClause .= "LOWER($key) " . $opmapping[$key] . " ";
    								$whereClause .= ":$key$i";
    								$params[$key.$i] = strtolower($val);
								} else {
								    $whereClause .= "$key " . $opmapping[$key] . " ";
								    $whereClause .= ":$key$i";
								    $params[$key.$i] = $val;
								}
								$i++;
							}
							$whereClause .= ")";
						} else {
							// Valeur simple avec LIKE
							if ($whereClause != "") $whereClause .= " AND ";
							if ($is_case_unsensitive) {
    							$whereClause .= "LOWER($key) " . $opmapping[$key] . " :$key";
    							$params[$key] = strtolower($this->$key);
							} else {
							    $whereClause .= "$key " . $opmapping[$key] . " :$key";
							    $params[$key] = $this->$key;
							}
						}
					} else {
						if (is_array($this->$key)) {
							// On est dans un tableau, pas de like, on utilise IN
							if ($whereClause != "") $whereClause .= " AND ";
							if ($is_case_unsensitive)
							    $whereClause .= "LOWER($key) IN (";
							else
							    $whereClause .= "$key IN (";
							$i = 1;
							foreach ($this->$key as $val) {
								if ($i > 1) $whereClause .= ", ";
								$whereClause .= ":$key$i";
								if ($is_case_unsensitive)
								    $params[$key.$i] = strtolower($val);
								else
								    $params[$key.$i] = $val;
								$i++;
							}
							$whereClause .= ")";
						} else {
							// Valeur simple, pas de like, on utilise l'égalité
							if ($whereClause != "") $whereClause .= " AND ";
							if ($is_case_unsensitive) {
    							$whereClause .= "LOWER($key) = :$key";
    							$params[$key] = strtolower($this->$key);
							} else {
							    $whereClause .= "$key = :$key";
							    $params[$key] = $this->$key;
							}
						}
					}
				}
			}
		}
		// Tri
		if (!empty($orderby)) {
		    // Récupèration des données de mapping
		    if (isset(MappingMce::$Data_Mapping[$this->objectType])
		            && isset(MappingMce::$Data_Mapping[$this->objectType][$orderby])) {
		        $orderby = MappingMce::$Data_Mapping[$this->objectType][$orderby][MappingMce::name];
		    }
		    $whereClause .= " ORDER BY $orderby" . ($asc ? " ASC" : " DESC");
		}
		// Limit
		if (isset($limit)) {
		    $whereClause .= " LIMIT $limit";
		}
		// Offset
		if (isset($offset)) {
		    $whereClause .= " OFFSET $offset";
		}
		// Chargement de la requête
		$query = Sql\SqlAttachmentRequests::getAttachmentsList;
		// Liste des champs
		if (!is_array($fields) && strtolower($fields) == 'count') {
		    // On fait un count(*)
		    $query = str_replace("{fields_list}", "count(*)", $query);
		} elseif (count($fieldsmapping) > 0) {
			$query = str_replace("{fields_list}", implode(", ", $fieldsmapping), $query);
		} else {
			$query = str_replace("{fields_list}", "*", $query);
		}
		// Clause where
		$query = str_replace("{where_clause}", $whereClause, $query);

		// Récupération
		return  Sql\DBMelanie::ExecuteQuery($query, $params, $this->get_class);
	}

	/**
	 * Fonction appelé après la génération de l'objet par PDO
	 * Cette fonction est normalement auto appelée par le getList
	 * Elle permet de définir les bon paramètres de l'objet
	 * L'appel externe n'est donc pas nécessaire (mais cette méthode doit rester public)
	 * @param bool $isExist si l'objet existe
	 */
	function pdoConstruct($isExist) {
		$this->initializeHasChanged();
		$this->isExist = $isExist;
	}
}