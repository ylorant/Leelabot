<?php
/**
 * \file tests/intl_parser/locale.php
 * \author Yohann Lorant <linkboss@gmail.com>
 * \version 0.5
 * \brief Intl_Parser class tests.
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
 * This file tests the behavior of the Intl_Parser class, and how it responds for a globally exhausting file/folder_parsing.
 * This file will NOT be further documented.
 */

include('../../core/intl.class.php');

$parser = new Intl_Parser();
print_r($parser->parseFile('data/core.lc'));
