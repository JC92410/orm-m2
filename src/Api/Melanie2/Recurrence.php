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
 * Classe recurrence pour Melanie2
 * Doit être lié à un objet Event pour écrire directement dans les API
 * Certains champs sont mappés directement
 *
 * @author Groupe Messagerie/MTES - Apitech
 * @package Librairie MCE
 * @subpackage API MCE
 * @api
 * 
 * @property string $enddate Date de fin de récurrence au format compatible DateTime
 * @property int $count Nombre d'occurrences
 * @property int $interval Interval de répétition de la récurrence
 * @property Recurrence::RECURTYPE_* $type Type de récurrence
 * @property Recurrence::RECURDAYS_* $days Jours de récurrence
 * @property array $rrule Parses an iCalendar 2.0 recurrence rule
 */
class Recurrence extends Mce\Recurrence {}