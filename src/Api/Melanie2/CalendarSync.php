<?php
/**
 * Ce fichier est développé pour la gestion de la librairie MCE
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
namespace LibMelanie\Api\Melanie2;

use LibMelanie\Api\Mce;

/**
 * Classe pour la gestion des Sync pour les calendriers
 * Certains champs sont mappés directement ou passe par des classes externes
 *
 * @author Groupe Messagerie/MTES - Apitech
 * @package Librairie MCE
 * @subpackage API MCE
 * @api
 * 
 * @property integer $token Numéro de token associé à l'objet Sync
 * @property string $calendar Identifiant du calendrier associé à l'objet Sync
 * @property string $uid UID de l'événement concerné par le Sync
 * @property string $action Action effectuée sur l'uid (add, mod, del)
 * 
 * @method bool load() Chargement du CalendarSync, en fonction du calendrier et du token
 * @method bool exists() Test si le CalendarSync existe, en fonction du calendrier et du token
 * @method CalendarSync[] listCalendarSync($syncToken, $limit) Permet de lister les CalendarSync d'un calendrier
 */
class CalendarSync extends Mce\CalendarSync {}
