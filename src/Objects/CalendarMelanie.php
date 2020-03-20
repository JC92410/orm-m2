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
use LibMelanie\Config\DefaultConfig;

/**
 * Traitement des calendriers Melanie2
 * @author PNE Messagerie/Apitech
 * @package Librairie Mélanie2
 * @subpackage ORM
 */
class CalendarMelanie extends MagicObject implements IObjectMelanie {
	/**
	 * Constructeur de l'objet, appelé par PDO
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
		$this->tableName = MappingMce::$Table_Name[$this->objectType];

		if (isset(MappingMce::$Primary_Keys[$this->objectType])) {
			if (is_array(MappingMce::$Primary_Keys[$this->objectType])) $this->primaryKeys = MappingMce::$Primary_Keys[$this->objectType];
			else $this->primaryKeys = [MappingMce::$Primary_Keys[$this->objectType]];
		}
	}

	/**
	 * Chargement de l'objet
	 * need: $this->id
	 * need: $this->user_uid
	 * @return boolean isExist
	 */
	function load() {
		M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class."->load()");
		// Initialisation du backend SQL
		Sql\DBMelanie::Initialize(ConfigSQL::$CURRENT_BACKEND);
		if (!isset($this->id)) return false;
		if (!isset($this->user_uid)) return false;
		// Test si l'objet existe, pas besoin de load
		if (is_bool($this->isExist) && $this->isLoaded) {
		  return $this->isExist;
		}
		$query = Sql\SqlMelanieRequests::listObjectsByUid;
		// Replace name
		$query = str_replace('{user_uid}', MappingMce::$Data_Mapping[$this->objectType]['owner'][MappingMce::name], $query);
		$query = str_replace('{datatree_name}', MappingMce::$Data_Mapping[$this->objectType]['id'][MappingMce::name], $query);
		$query = str_replace('{datatree_ctag}', MappingMce::$Data_Mapping[$this->objectType]['ctag'][MappingMce::name], $query);
		$query = str_replace('{datatree_synctoken}', MappingMce::$Data_Mapping[$this->objectType]['synctoken'][MappingMce::name], $query);
		$query = str_replace('{attribute_value}', MappingMce::$Data_Mapping[$this->objectType]['name'][MappingMce::name], $query);
		$query = str_replace('{perm_object}', MappingMce::$Data_Mapping[$this->objectType]['perm'][MappingMce::name], $query);
		$query = str_replace('{datatree_id}', MappingMce::$Data_Mapping[$this->objectType]['object_id'][MappingMce::name], $query);

		// Params
		$params = [
				"group_uid" => DefaultConfig::CALENDAR_GROUP_UID,
				"user_uid" => $this->user_uid,
				"datatree_name" => $this->id,
		    "attribute_name" => DefaultConfig::ATTRIBUTE_NAME_NAME,
		    "attribute_perm" => DefaultConfig::ATTRIBUTE_NAME_PERM,
		    "attribute_permfg" => DefaultConfig::ATTRIBUTE_NAME_PERMGROUP,
		];

		// Liste les calendriers de l'utilisateur
		$this->isExist = Sql\DBMelanie::ExecuteQueryToObject($query, $params, $this);
		if ($this->isExist) {
			$this->initializeHasChanged();
		}
		// Les données sont chargées
		$this->isLoaded = true;
		return $this->isExist;
	}

	/**
	 * Sauvegarde le calendrier
	 * @return boolean True si c'est une command Insert, False si c'est un Update
	 */
	function save () {
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
			if (isset($this->haschanged[MappingMce::$Data_Mapping[$this->objectType]['name'][MappingMce::name]])
					&& $this->haschanged[MappingMce::$Data_Mapping[$this->objectType]['name'][MappingMce::name]]) {
				$this->saveName();
			}
		} else {
			if (!isset($this->user_uid)) return false;
			// C'est une Insertion
			$insert = true;
			Sql\DBMelanie::BeginTransaction();
			$query = Sql\SqlMelanieRequests::insertObject;
			$res = Sql\DBMelanie::ExecuteQuery(Sql\SqlMelanieRequests::getNextObject);
			$datatree_id = $res[0][0];
			$datatree_name = isset($this->id) ? $this->id : md5(time() . $datatree_id);
			$params = [
					'datatree_id' => $datatree_id,
					'datatree_name' => $datatree_name,
					'datatree_ctag' => md5($datatree_name),
					'user_uid' => $this->user_uid,
			    'group_uid' => isset($this->group) ?  $this->group : DefaultConfig::CALENDAR_GROUP_UID,
			];
			if (Sql\DBMelanie::ExecuteQuery($query, $params)) {
				$this->isExist = true;
				// Name
				$query = Sql\SqlObjectPropertyRequests::insertProperty;
				$params = [
						'datatree_id' => $datatree_id,
				    'attribute_name' => DefaultConfig::ATTRIBUTE_NAME_NAME,
						'attribute_key' => '',
						'attribute_value' => isset($this->name) ?  $this->name : $datatree_name,
				];
			    if (!Sql\DBMelanie::ExecuteQuery($query, $params)) {
			        Sql\DBMelanie::Rollback();
			        return null;
				}
				// owner
				$query = Sql\SqlObjectPropertyRequests::insertProperty;
				$params = [
						'datatree_id' => $datatree_id,
				    'attribute_name' => DefaultConfig::ATTRIBUTE_OWNER,
						'attribute_key' => '',
						'attribute_value' => $this->user_uid,
				];
			    if (!Sql\DBMelanie::ExecuteQuery($query, $params)) {
			        Sql\DBMelanie::Rollback();
			        return null;
				}
				// perm
				$query = Sql\SqlObjectPropertyRequests::insertProperty;
				$params = [
						'datatree_id' => $datatree_id,
				    'attribute_name' => DefaultConfig::ATTRIBUTE_NAME_PERM,
						'attribute_key' => $this->user_uid,
						'attribute_value' => '30',
				];
			    if (!Sql\DBMelanie::ExecuteQuery($query, $params)) {
			        Sql\DBMelanie::Rollback();
			        return null;
				}
				Sql\DBMelanie::Commit();
			} else {
			    Sql\DBMelanie::Rollback();
			    return null;
			}
		}
		if ($this->isExist) $this->initializeHasChanged();
		return $insert;
	}

	/**
	 * (non-PHPdoc)
	 * @see IObjectMelanie::delete()
	 */
	function delete() {
		M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class."->exists()");
		// Initialisation du backend SQL
		Sql\DBMelanie::Initialize(ConfigSQL::$CURRENT_BACKEND);
		if (!isset($this->tableName)) return false;

		// Si l'objet existe on fait un UPDATE
		if ($this->isExist
				&& isset($this->object_id)) {
			// Params
			$params = [
					"datatree_id" => $this->object_id,
			];
			$ok = true;
			Sql\DBMelanie::BeginTransaction();
			$query = Sql\SqlMelanieRequests::deleteObject1;
			// Supprimer l'objet
			$ok &= Sql\DBMelanie::ExecuteQuery($query, $params);
			$query = Sql\SqlMelanieRequests::deleteObject2;
			// Supprimer l'objet
			$ok &= Sql\DBMelanie::ExecuteQuery($query, $params);
			$query = Sql\SqlMelanieRequests::deleteObject3;
			$query = str_replace("{objects_table}", "kronolith_events", $query);
			$query = str_replace("{datatree_name}", MappingMce::$Data_Mapping[$this->objectType]['id'][MappingMce::name], $query);
			// Params
			$params = [
			    "datatree_name" => $this->id,
			];
			// Supprimer l'objet
			$ok &= Sql\DBMelanie::ExecuteQuery($query, $params);
			// Ne pas supprimer du horde_histories qui part en timeout sur la prod
			// TODO: Trouver une solution
//  			$query = Sql\SqlMelanieRequests::deleteObject4;
//  			// Params
//  			$params = [
//  					"object_uid" => DefaultConfig::CALENDAR_PREF_SCOPE.":".$this->id.":%",
//  			];
//  			// Supprimer l'objet
//  			$ok &= Sql\DBMelanie::ExecuteQuery($query, $params);
      if ($ok) {
        Sql\DBMelanie::Commit();
        $this->initializeHasChanged();
        $this->isExist = false;
      }
      else Sql\DBMelanie::Rollback();
			return $ok;
		}
		return false;
	}

	/**
	 * (non-PHPdoc)
	 * @see IObjectMelanie::exists()
	 */
	function exists() {
		M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class."->exists()");
		// Initialisation du backend SQL
		Sql\DBMelanie::Initialize(ConfigSQL::$CURRENT_BACKEND);
		// Si les clés primaires et la table ne sont pas définies, impossible de charger l'objet
		if (!isset($this->tableName)) return false;
		// Test si l'objet existe, pas besoin de load
		if (is_bool($this->isExist)) {
		  return $this->isExist;
		}
		// Paramètres de la requête
		$params = ['id' => $this->id, 'group' => $this->group];
		$whereClause = "datatree_name = :id AND group_uid = :group";

		$query = Sql\SqlObjectRequests::getObject;
		// Liste des champs
		$query = str_replace("{fields_list}", MappingMce::$Data_Mapping[$this->objectType]['object_id'][MappingMce::name], $query);
		// Nom de la table
		$query = str_replace("{table_name}", $this->tableName, $query);
		// Clause where
		$query = str_replace("{where_clause}", $whereClause, $query);

		// Liste les objets
		$res = Sql\DBMelanie::ExecuteQuery($query, $params);
		$this->isExist = (count($res) >= 1);
		return $this->isExist;
	}

	/**
	 * Fonction appelé après la génération de l'objet par PDO
	 * Cette fonction est normalement auto appelée par le getList
	 * Elle permet de définir les bon paramètres de l'objet
	 * L'appel externe n'est donc pas nécessaire (mais cette méthode doit rester public)
	 * @param bool $isExist si l'objet existe
	 */
	function pdoConstruct($isExist) {
		M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class."->pdoConstruct($isExist)");
		$this->initializeHasChanged();
		$this->isExist = $isExist;
	}

	/**
	 * Récupère la liste de tous les évènements
	 * need: $this->id
	 * @return boolean
	 */
	function getAllEvents() {
		M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class."->getAllEvents()");
		// Initialisation du backend SQL
		Sql\DBMelanie::Initialize(ConfigSQL::$CURRENT_BACKEND);
		if (!isset($this->id)) return false;

		// Params
		$params = [MappingMce::$Data_Mapping[$this->objectType]['id'][MappingMce::name] => $this->id];

		// Replace
		$query = str_replace("{event_range}", "", Sql\SqlCalendarRequests::listAllEvents);

		// Liste les evenements du calendrier
		return Sql\DBMelanie::ExecuteQuery($query, $params, 'LibMelanie\Objects\EventMelanie');
	}

	/**
	 * Récupère la liste des évènements entre start et end
	 * need: $this->id
	 * @param string $start Date de début
	 * @param string $end Date de fin
	 * @param int $modified Date de derniere modification des événements
	 * @param boolean $is_freebusy Est-ce que l'on cherche des freebusy
	 * @return boolean
	 */
	function getRangeEvents($event_start = null, $event_end = null, $modified = null, $is_freebusy = false) {
		M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class."->getRangeEvents($event_start, $event_end)");
		// Initialisation du backend SQL
		Sql\DBMelanie::Initialize(ConfigSQL::$CURRENT_BACKEND);
		if (!isset($this->id)) return false;
		// DateTime
		if (isset($event_start)) {
			$start = new \DateTime($event_start);
			$event_start = $start->format("Y-m-d H:i:s");
		}
		if (isset($event_end)) {
			$end = new \DateTime($event_end);
			$event_end = $end->format("Y-m-d H:i:s");
		}
		// Params
		$params = [MappingMce::$Data_Mapping[$this->objectType]['id'][MappingMce::name] => $this->id];

		// Range
		$event_range = "";
		if (isset($event_end) && isset($event_start)) {
		  $event_range .= " AND ((k1.event_start >= :event_start AND k1.event_start <= :event_end) OR (k1.event_end >= :event_start AND k1.event_end <= :event_end) OR (k1.event_end >= :event_end AND k1.event_start <= :event_start) OR (k1.event_recurtype >= 1 AND k1.event_recurenddate >= :event_start AND k1.event_end <= :event_end))";
		  $params['event_end'] = $event_end;
		  $params['event_start'] = $event_start;
		}
		else if (isset($event_end)) {
			$event_range .= " AND (k1.event_start <= :event_end OR (k1.event_recurtype >= 1 AND k1.event_recurenddate <= :event_end))";
			$params['event_end'] = $event_end;
		}
		else if (isset($event_start)) {
			$event_range .= " AND (k1.event_end >= :event_start OR (k1.event_recurtype >= 1 AND k1.event_recurenddate >= :event_start))";
			$params['event_start'] = $event_start;
		}
		if (isset($modified)) {
		  $event_range .= " AND k1.event_modified >= :modified";
		  $params['modified'] = $modified;
		}

		// Gestion de la requête
		if ($is_freebusy) {
		  $query = Sql\SqlCalendarRequests::listAllEventsFreebusy;
		}
		else {
		  $query = Sql\SqlCalendarRequests::listAllEvents;
		}

		// Replace
		$query = str_replace("{event_range}", $event_range, $query);

		// Liste les evenements du calendrier
		return Sql\DBMelanie::ExecuteQuery($query, $params, 'LibMelanie\Objects\EventMelanie');
	}

	/**
	 * Recupère le Tag associé à l'agenda
	 * need: $this->calendar_id
	 * @return string
	 */
	function getCTag() {
		M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class."->getCTag()");
		// Initialisation du backend SQL
		Sql\DBMelanie::Initialize(ConfigSQL::$CURRENT_BACKEND);
		if (!isset($this->id)) return false;

		if (!isset($this->ctag)) {
			// Params
			$params = [MappingMce::$Data_Mapping[$this->objectType]['id'][MappingMce::name] => $this->id];

			// Récupération du tag
			Sql\DBMelanie::ExecuteQueryToObject(Sql\SqlCalendarRequests::getCTag, $params, $this);
			if (!isset($this->ctag)) $this->ctag = md5($this->id);
			M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class."->getCTag() this->ctag: " . $this->ctag);
		}
		return $this->ctag;
	}

	/**
	 * Recupère le timezone par défaut pour le
	 * need: $this->user_uid
	 */
	function getTimezone() {
		M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class."->getTimezone()");
		// Initialisation du backend SQL
		Sql\DBMelanie::Initialize(ConfigSQL::$CURRENT_BACKEND);
		if (!isset($this->user_uid)) return DefaultConfig::CALENDAR_DEFAULT_TIMEZONE;

		if (!isset($this->timezone)) {
			// Replace name
			$query = str_replace('{pref_name}', 'timezone', Sql\SqlMelanieRequests::getUserPref);

			// Params
			$params = [
					"user_uid" => $this->user_uid,
			    "pref_scope" => DefaultConfig::PREF_SCOPE,
			    "pref_name" => DefaultConfig::TZ_PREF_NAME
			];

			// Récupération du timezone
			$res = Sql\DBMelanie::ExecuteQueryToObject($query, $params, $this);
			// Test si le timezone est valide en PHP
			try {
				$tz = new \DateTimeZone($this->timezone);
			} catch (\Exception $ex) {
			  $this->timezone = DefaultConfig::CALENDAR_DEFAULT_TIMEZONE;
			}
			M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class."->getTimezone() this->timezone: " . $this->timezone);
		}
		return $this->timezone;
	}
	/**
	 * Sauvegarde le nom de l'objet
	 */
	private function saveName() {
		// Si l'objet existe on fait un UPDATE
		if ($this->isExist
				&& isset($this->object_id)
				&& isset($this->name)) {
			$query = Sql\SqlObjectPropertyRequests::updateProperty;
			// Params
			$params = [
					"datatree_id" => $this->object_id,
					"attribute_value" => $this->name,
			    "attribute_name" => DefaultConfig::ATTRIBUTE_NAME_NAME,
			];
			Sql\DBMelanie::ExecuteQuery($query, $params);
		}
	}

	/**
	 * Gestion des droits
	 * @param string $action
	 * @return boolean
	 */
	function asRight($action) {
		M2Log::Log(M2Log::LEVEL_DEBUG, $this->get_class."->asRight($action)");
		return (DefaultConfig::$PERMS[$action] & $this->perm_calendar) === DefaultConfig::$PERMS[$action];
	}
}