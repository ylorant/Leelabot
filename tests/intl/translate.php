<?php
/**
 * \file tests/intl/translate.php
 * \author Yohann Lorant <linkboss@gmail.com>
 * \version 0.5
 * \brief Intl class tests.
 *
 * \section LICENSE
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * General Public License for more details at
 * http://www.gnu.org/copyleft/gpl.html
 *
 * \section DESCRIPTION
 *
 * This file tests the behavior of the Intl class, and how it responds for a translation.
 * This file will NOT be further documented.
 */

include('../../core/intl.class.php');

$intl = new Intl();
if(!$intl)
	exit("Failure.\n");

if(!$intl->setRoot('data'))
	exit("Failure.\n");

if(!$intl->setLocale('fr'))
	exit("Failure.\n");

echo $intl->translate('startup')."\n";
echo $intl->translate('shutdown')."\n";
