<?php
/**
 * Ce fichier est développé pour la gestion de la lib MCE
 * 
 * Cette Librairie permet d'accèder aux données sans avoir à implémenter de couche SQL
 * Des objets génériques vont permettre d'accèder et de mettre à jour les données
 * 
 * ORM Mél Copyright © 2020 Groupe Messagerie/MTES
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
namespace LibMelanie\Lib;

use LibMelanie\Exceptions;
use Serializable;

/**
 * Objet MCE, implémente les getter/setter pour le mapping des données
 *
 * @author Groupe Messagerie/MTES - Apitech
 * @package LibMCE
 * @subpackage Lib
 */
abstract class MceObject implements Serializable {
	/**
	 * Objet Mel
	 */
	protected $objectmelanie;
	
	/**
	 * Classe courante
	 * @var string
	 */
	protected $get_class;

	/**
	 * Namespace de la classe courante
	 * 
	 * @var string
	 */
	private $_namespace;

	/**
	 * Liste des callback enregistrés pour le cache
	 */
	private $_cacheCallbacks;

	/**
	 * Liste des propriétés à sérialiser pour le cache
	 */
	protected $serializedProperties = [];

	/**
	 * Récupère le namespace de la classe courante en fonction du get_class
	 * L'utilisateur de __NAMESPACE__ n'est pas possible pour un héritage
	 * 
	 * @return string Namespace courant
	 */
	public function __getNamespace() {
		if (!isset($this->_namespace)) {
			$class = $this->get_class;
			$class = explode('\\', $class);
			array_pop($class);
			$this->_namespace = implode('\\', $class);
		}
		return $this->_namespace;
	}

	/**
	 * Défini l'objet Melanie
	 * 
	 * @param ObjectMelanie $objectmelanie
	 * 
	 * @ignore
	 */
	public function setObjectMelanie($objectmelanie) {
		$this->objectmelanie = $objectmelanie;
	}

	/**
	 * Récupère l'objet Melanie
	 * 
	 * @ignore
	 */
	public function getObjectMelanie() {
		if (!isset($this->objectmelanie)) throw new Exceptions\ObjectMelanieUndefinedException();
		return $this->objectmelanie;
	}

	/**
	 * Est-ce que l'object melanie est bien positionné ?
	 * 
	 * @ignore
	 */
	public function issetObjectMelanie() {
		return isset($this->objectmelanie);
	}

	/**
	 * PHP magic to set an instance variable
	 *
	 * @param string $name Nom de la propriété
	 * @param mixed $value Valeur de la propriété
	 * @access public
	 * @return
	 * @ignore
	 */
	public function __set($name, $value) {
		$lname = strtolower($name);
		$uname = ucfirst($lname);

		// Call SetMapProperty
		if (method_exists($this, "setMap$uname")) {
			$this->{"setMap$uname"}($value);
		} else if (isset($this->objectmelanie)) {
			$this->objectmelanie->$lname = $value;
		}
	}

	/**
	 * PHP magic to get an instance variable
	 *
	 * @param string $name Nom de la propriété
	 * @access public
	 * @return
	 * @ignore
	 */
	public function __get($name) {
		$lname = strtolower($name);
		$uname = ucfirst($lname);

		// Call GetMapProperty
		if (method_exists($this, "getMap$uname")) {
			return $this->{"getMap$uname"}();
		} elseif (isset($this->objectmelanie) && isset($this->objectmelanie->$lname)) {
			return $this->objectmelanie->$lname;
		}
		return null;
	}

	/**
	 * PHP magic to check if an instance variable is set
	 *
	 * @param string $name Nom de la propriété
	 * @access public
	 * @return
	 * @ignore
	 */
	public function __isset($name) {
	    $lname = strtolower($name);
	    $uname = ucfirst($lname);

	    if (method_exists($this, "issetMap$uname")) {
	        return $this->{"issetMap$uname"}();
	    } else {
	        return isset($this->objectmelanie) && isset($this->objectmelanie->$lname);
	    }
	}

	/**
	 * PHP magic to remove an instance variable
	 *
	 * @param string $name Nom de la propriété
	 * @access public
	 * @return
	 * @ignore
	 */
	public function __unset($name) {
		$lname = strtolower($name);
		if (isset($this->objectmelanie) && isset($this->objectmelanie->$lname)) {
			unset($this->objectmelanie->$lname);
		}
	}

	/**
	 * PHP magic to implement any getter, setter, has and delete operations
	 * on an instance variable.
	 * Methods like e.g. "SetVariableName($x)" and "GetVariableName()" are supported
	 *
	 * @param string $name Nom de la methode
	 * @param array $arguments Arguments de la methode
	 * @access public
	 * @return mixed
	 * @ignore
	 */
	public function __call($name, $arguments) {
		$name = strtolower($name);
		// Call method
		if (method_exists($this, $name)) {
			return call_user_func_array([$this, $name], $arguments);
		} else if (isset($this->objectmelanie)) {
			return call_user_func_array([$this->objectmelanie, $name], $arguments);
		}
	}

	/**
	 * String representation of object
	 *
	 * @return string
	 */
	public function serialize() {
		$array = [];
		foreach ($this->serializedProperties as $prop) {
			$array[$prop] = $this->$prop;
		}
		$array['_namespace'] = $this->_namespace;
		$array['get_class'] = $this->get_class;
		$array['objectmelanie'] = serialize($this->objectmelanie);
		return serialize($array);
	}

	/**
	 * Constructs the object
	 *
	 * @param string $serialized
	 * 
	 * @return void
	 */
	public function unserialize($serialized) {
		$array = unserialize($serialized);
		if ($array) {
			$this->_namespace = $array['_namespace'];
			$this->get_class = $array['get_class'];
			$this->objectmelanie = unserialize($array['objectmelanie']);
			foreach ($this->serializedProperties as $prop) {
				$this->$prop = $array[$prop];
			}
		}
	}

	/**
	 * Enregistrement des methodes de gestion du cache
	 */
	public function registerCache($app, $callback) {
		if (!isset($this->_cacheCallbacks)) {
			$this->_cacheCallbacks = [];
		}
		$this->_cacheCallbacks[$app] = $callback;
	}

	/**
	 * Lancement des methodes de gestion du cache (nouveau contenu)
	 */
	public function executeCache() {
		if (is_array($this->_cacheCallbacks)) {
			foreach ($this->_cacheCallbacks as $callback) {
				call_user_func($callback);
			}
		}
	}
}