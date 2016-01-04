<?php

/**
* Override configuration file for a Heurist installation - place in parent of the individual codebases,
* obviating the need to set these parameters for each version, avoiding redundancy and ensuring consistency
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2015 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

// See configIni.php for documentation. If this file is placed in the parent directory of Heurist, eg. the web root,
// and renamed heuristConfigIni.php, the values in this file will overide those given (if any) in the
// configIni.php file in each copy of Heurist

if (!@$serverName) $serverName = null; // override default taken from request header SERVER_NAME

// ------ VALUES YOU SHOULD SET -------------------------------------------------------------------------------------------

if (!@$dbHost) {
	$dbHost= ""; //optional, blank = localhost
	$dbAdminUsername = "";  // MySQL user names and passwords - required
	$dbAdminPassword = ""; // required
	$dbReadonlyUsername = ""; // required
	$dbReadonlyPassword = ""; //required
}

if (!@$sysAdminEmail) $sysAdminEmail = ""; // recommended
if (!@$infoEmail) $infoEmail = ""; // recommended
if (!@$bugEmail) $bugEmail = ""; // recommended, set to info@heuristNetwork.org if your server is running a standard Heurist installation


// -------- THE REST IS OPTIONAL ----------------------------------------------------------------------------------------------

if (!@$dbPrefix) $dbPrefix = "hdb_"; // recommend retaining hdb_

if (!@$indexServerAddress) $indexServerAddress = ""; // set to IP address of Elastic search server, if used
if (!@$indexServerPort) $indexServerPort = "9200";

if (!@$defaultDBname) $defaultDBname = ""; // not required, generally best left blank
if (!@$httpProxy) $httpProxy = ""; // if access to the outside world is through a proxy
if (!@$httpProxyAuth) $httpProxyAuth = ""; // ditto
if (!@$passwordForDatabaseCreation) $passwordForDatabaseCreation=""; // normally blank = any logged in user can create

if (!@$defaultRootFileUploadPath) $defaultRootFileUploadPath = "/var/www/html/HEURIST/HEURIST_FILESTORE/"; // default location for new installations
                                                                   // defines Heurist filestore - location of files associated with databases

if (!@$websiteThumbnailService) $websiteThumbnailService = "http://immediatenet.com/t/m?Size=1024x768&URL=[URL]";
if (!@$websiteThumbnailUsername) $websiteThumbnailUsername = "";
if (!@$websiteThumbnailPassword) $websiteThumbnailPassword = "";
if (!@$websiteThumbnailXsize) $websiteThumbnailXsize = 500; // required
if (!@$websiteThumbnailYsize) $websiteThumbnailYsize = 300; // required

?>