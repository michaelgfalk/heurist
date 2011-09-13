<?php
    /*<!--
    * buildCrosswalks.php, Gets definitions from a specified installation of Heurist and writes them
    * either to a new DB, or temp DB, 18-02-2011, by Juan Adriaanse
    * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
    * @link: http://HeuristScholar.org
    * @license http://www.gnu.org/licenses/gpl-3.0.txt
    * @package Heurist academic knowledge management system
    * @todo
    -->*/

    // crosswalk_builder.php  - gets definitions from a specified installation of Heurist
    // Processes them into local definitions, allows the administrator to import definitions
    // and stores equivalences in the def_crosswalks table.

    // started: Ian Johnson 3 March 2010. Revised Ian Johnson 26 sep 2010 14.15 to new table/field names
    // and added selection of definitions to be imported and crosswalk builder, plus instructions and pseudocode.
    // 4 Aug 2011, changed to import table structures from blankDBStructure.sql and to
    // include crosswalking during creation of a new database

    // Notes and directions:

    // This version simply imports definitions. It does not look for existing similar definitions and does not
    // allow any sort of combination of definitions. In a smarter next version we might add the ability
    // to show similar record types (based on fuzzy name matching and/or identification of original source
    // as being the same) next to each of the import candidates, so people will be less inclined to import
    // several very similar record type definitions.

    // The same could be done for detail types, allowing the admin to re-use an existing detail type rather than
    // creating a new one, but they will have to be of the same type eg. text, numeric, date etc. and there
    // could be a problem where vocabs and constraints are involved since the existing vocabs might not have all
    // the enum values required by the constraint.

    // Once this version is up and running, we need either a variant, or to add the capability to this one, of
    // matching and writing the crosswalk for record types, detail types, vacabularies and enums (not for constraints)
    // without importing new definitions, in other words just setting up the crosswalk to be able to send queries
    // and/or download data from another instance.

    require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');

    if (!is_logged_in()) {
        header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php?db='.HEURIST_DBNAME);
        return;
    }

    // ------Administrative stuff ------------------------------------------------------------------------------------

    // Verify credentials of the user and check that they are an administrator, check no-one else is trying to
    // change the definitions at the same time

    // Requires admin user, access to definitions though get_definitions is open
    if (! is_admin()) {
        print "<html><head><link rel=stylesheet href='../../common/css/global.css'>
        </head><body><div class=wrap><div id=errorMsg>
        <span>You do not have sufficient privileges to access this page</span><p>
        <a href=".HEURIST_URL_BASE."common/connect/login.php?logout=1&amp;db=".HEURIST_DBNAME.
        " target='_top'>Log out</a></p></div></div></body></html>";
        return;
    }

    require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

    global $errorCreatingTables;
    $errorCreatingTables = FALSE;

    // Deals with all the database connections stuff
    mysql_connection_db_insert(DATABASE);

    global $dbname;
    global $tempDBName;

    if(!isset($isNewDB)) { $isNewDB = false; }
    $isExistingDB = !$isNewDB; // for clarity

    if($isNewDB)
    { // For new database, insert coreDefinitions.txt directly into tables, no temp database required
		$tempDBName =$newname;
        $dbname = $newname;
    } Else { // existing database needs temporary database to store data read and allow selection
        $dbname = DATABASE;
        $isNewDB = false;
		$tempDBName = "temp_".$dbname;
    } // existing database

error_log(" tempdbname = $tempDBName  is new = $isNewDB  dbname = $dbname");
    // * IMPORTANT *
    //   If database format is changed, update version info, include files, sql fiels for new dbs etc.
    // see comprehensive lsit in admin/structure/getDBStrucutre.php




    // -----Check not locked by admin -------------------------------

    // THIS SECTION SHOULD BE ABSTRACTED AS A FUNCTION IN ONE OF THE LIBRARIES, perhaps in cred.php?

    // ???? we should now mark the target (current)database for administrator access to avoid two administrators
    // working on this at the same time. But need to provide a means of removing lock in case the
    // connection is lost, eg. heartbeat on subsequent pages or a specific 'remove admin lock' link (easier)

    // Check if someone else is already modifying database definitions, if so: stop.

	if ($isExistingDB) {
    $res = mysql_query("select lck_UGrpID from sysLocks where lck_Action='buildcrosswalks'");
    // 6/9/11 $res is not being recognised as a valid MySQL result, and always returns false. This appear to be identical
    // to example in help. So the following test is not being processed and the lock is ignored. The query works in MySQL
    // TODO: get this locking mechanism to work

    if (($res && mysql_num_rows($res)>0)) { // SQL OK and there is a lock record
        // error log says “supplied argument is not a valid MySQL result resource”
        echo "Definitions are already being modified or SQL failure on lock check.";
        header('Location: ' . BASE_PATH . 'common/html/msgLockedByAdmin.html'); // put up informative failure message
        die("Definitions are already being modified.<p> If this is not the case, you will need to delete the lock record in sysLocks table. <br>Consult Heurist team for assistance if needed");
    } // detect lock and shuffle out

    // Mark database definitons as being modified by adminstrator
		$query = "insert into sysLocks (lck_ID, lck_UGrpID, lck_Action) VALUES ('1', '".(function_exists('get_user_id') ? get_user_id(): 0)."', 'buildcrosswalks')";
    $res = mysql_query($query); // create sysLock

		// Create the Heurist structure for the temp database, using a stripepd version of the new database template
		mysql_query("DROP DATABASE IF EXIST`" . $tempDBName . "`");	// database might exist from previous use
		mysql_query("CREATE DATABASE `" . $tempDBName . "`"); // TODO: should check database is created
		$cmdline="mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD.
		" -D$tempDBName < ../setup/createDefinitionTablesOnly.sql"; // subset of, and must be kept in sync with, blankDBStructure.sql
		$output2 = exec($cmdline . ' 2>&1', $output, $res2);
		if($res2 != 0) {
			mysql_query("DROP DATABASE `" . $tempDBName . "`");
			die("MySQL exec code $res2 : Unable to create table structure for new database $tempDBName (failure in executing createDefinitionTablesOnly.sql)");
		}
	}

	mysql_connection_db_insert($tempDBName); // Use temp database


    // ------Find and set the source database-----------------------------------------------------------------------

    // Query heuristscholar.org Index database to find the URL of the installation you want to use as source
    // The query should be based on DOAP metadata and keywords which Steven is due to set up in the Index database



	if($isNewDB) { // minimal definitions from coreDefinitions.txt - format same as getDBStructure returns
		$file = fopen("../setup/coreDefinitions.txt", "r");
		while(!feof($file)) {
			$output = $output . fgets($file, 4096);
		}
		fclose($file);
		$data = $output;
	} else { // Request data from source database using getDBStructure.php
    //  Set information about the database you will be importing from
    global $source_db_id;
    if(!isset($_REQUEST["dbID"]) || $_REQUEST["dbID"] == 0) {
        // TODO: THIS SHOULD NOT HAPPEN, would be better to issue a warning and exit
        // TODO: check that this poitns at the correct reference database
        $source_db_id = '2'; //MAGIC NUMBER - ID of HeuristSystem_Reference db in Heurist_System_Index database
        $source_db_name = 'H3CoreDefinitions';
        $source_db_prefix = 'hdb_';
        $source_url = "http://heuristscholar.org/h3/admin/structure/getDBStructure.php?db=".$source_db_name.(@$source_db_prefix?"&prefix=".$source_db_prefix:"");
        // parameters were ?prefix=hdb_&db=H3CoreDefinitions";
    } else {
        $source_db_id = $_REQUEST["dbID"];
        $source_db_name = $_REQUEST["dbName"];
        $source_db_prefix = @$_REQUEST["dbPrefix"] && @$_REQUEST["dbPrefix"] != "" ? @$_REQUEST["dbPrefix"] : null;
        $source_url = $_REQUEST["dbURL"]."admin/structure/getDBStructure.php?db=".$source_db_name.(@$source_db_prefix?"&prefix=".$source_db_prefix:"");
    }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_COOKIEFILE, '/dev/null');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);	//return curl_exec output as string
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
        curl_setopt($ch, CURLOPT_NOBODY, 0);
        curl_setopt($ch, CURLOPT_HEADER, 0);	//don't include header in output
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);	// follow server header redirects
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);	// don't verify peer cert
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);	  // timeout after ten seconds
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);	   // no more than 5 redirections
        curl_setopt($ch, CURLOPT_URL,$source_url);
        $data = curl_exec($ch);
        $error = curl_error($ch);
        if ($error || !$data || substr($data, 0, 6) == "unable") {
            $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
			mysql_query("DROP DATABASE IF EXIST`" . $tempDBName . "`");
			//unlock
			mysql_query("delete from sysLocks where lck_ID='1'"); // Remove sysLock
        die("<br>Source database <b> $source_db_id : $source_db_prefix$source_db_name </b>could not be accessed <p>URL to structure service: <a href=$source_url target=_blank>$source_url</a> <p>Server may be offline");
    }
	} // getting data from source database for import of definitions to an existing database

    // Split received data into data sets for one table defined by >>StartData>> and >>EndData>> markers.

    $startToken = ">>StartData>>"; // also defined in getDBStructure.php

    $splittedData = split($startToken, $data);
    $tableNumber =1;

    preg_match("/Database Version:\s*(\d+)\.(\d+)(?:\.(\d+))*/",$data,$sourceDBVersion); // $sourceDBVersion[0] = version string, 1, 2, 3 = ,major, minor, sub versions

    preg_match("/Vsn:\s*(\d+)\.(\d+)(?:\.(\d+))*/","Vsn: ".HEURIST_DBVERSION,$thisDBVersion); // $sourceDBVersion[0] = version string, 1, 2, 3 = ,major, minor, sub versions

        if (!($sourceDBVersion[1] == $thisDBVersion[1] && $sourceDBVersion[2] == $thisDBVersion[2])) {
        echo "<p><strong>The source database ($sourceDBVersion[0]) is a different major/minor version from the current database (Vsn ".HEURIST_DBVERSION.
             ")</strong><p>One or other database will need updating to the same major/minor version #";
        exit();
        }

    function getNextDataSet($splittedData) { // returns and removes the first set of data between markers from $splitteddata
        global $tableNumber;
        $endToken = ">>EndData>>"; // also defined in getDBStructure.php
        if(!$tableNumber) {
           $tableNumber = 1;
        }
        // TODO: this is a horrible approach to splitting out the data. Should be rewritten. Works, so for the moment if it ain't broke ...
        if(sizeof($splittedData) > $tableNumber) { // what the hell does this do? fortunately it is always true!
            $splittedData2 = split($endToken, $splittedData[$tableNumber]);
            $i = 1;
            $size = strlen($splittedData2[0]);
            $testData = $splittedData2[0];
            if(!($testData[$size - $i] == ")")) {
                while((($size - $i) > 0) && (($testData[$size - $i]) != ")")) {
                    if($i == 10) {
                        $i = -1;
                        break;
                    }
                    $i++;
                }
            }
            if($i != -1) {
                $i--;
                $splittedData3 = substr($splittedData2[0],0,-$i);
            }
            $tableNumber++;
            return $splittedData3;
        } else {
            return null;
        }
    } // getNextDataSet

    // Do the splits and place in arrays
    // Note, these MUST be in the same order as getDBStructure

    $recTypeGroups = getNextDataSet($splittedData);
    $detailTypeGroups = getNextDataSet($splittedData);
    $ontologies = getNextDataSet($splittedData);
    $terms = getNextDataSet($splittedData);
    $recTypes = getNextDataSet($splittedData);
    $detailTypes = getNextDataSet($splittedData);
    $recStructure = getNextDataSet($splittedData);
    $relationshipConstraints = getNextDataSet($splittedData);
    $fileExtToMimetype = getNextDataSet($splittedData);
    $translations = getNextDataSet($splittedData);
    // we are not extracting defCalcFunctions, defCrosswalk, defLanguage, defURLPrefixes, users, groups and tags
    // add later if needed

    // insert the arrays into the corresonding tables (new db) or temp tables (existing)
    $query = "SET SESSION sql_mode='NO_AUTO_VALUE_ON_ZERO'";
    mysql_query($query);
    processRecTypeGroups($recTypeGroups);
    processDetailTypeGroups($detailTypeGroups);
    processOntologies($ontologies);
    processTerms($terms);
    processRecTypes($recTypes);
    processDetailTypes($detailTypes);
    processRecStructure($recStructure);
    processRelationshipConstraints($relationshipConstraints);
    processFileExtToMimetype($fileExtToMimetype);
    processTranslations($translations);
    $query = "SET SESSION sql_mode=''";
    mysql_query($query);

    // TODO: Make sure all values are written correctly (especially the NULL values)

    // ------ Functions to write source DB definitions to local tables ---------------------------------------------------

    // These insert statements updated by Ian ~12/8/11

    // NOTE: It is ESSENTIAL that the insert statements here correspond in fields and in order with the
    //       tables being written out by getDBStructure
    //       Some tables not processed (defCalcFunctions, defCrosswalk, defLanguages, sysIdentification and UGrps and tags)


    function processRecTypes($dataSet) {
        global $errorCreatingTables;
        if(!(($dataSet == "") || (strlen($dataSet) <= 2))) { // no action if no data
            include "crosswalk/defRecTypesFields.inc";
			//  debugStop($dataSet);
            $query = "INSERT INTO `defRecTypes` ($flds) VALUES" . $dataSet;
            mysql_query($query);
            if(mysql_error()) {
                echo "RECTYPES Error inserting data: " . mysql_error() . "<p>FIELDS:$flds<br /><p>VALUES:$dataSet<p>";
                $errorCreatingTables = TRUE;
            }
        } // END Imported first set of data to temp table: defRectypes
    } // processRecTypes


    function processDetailTypes($dataSet) {
        global $errorCreatingTables;
        if(!(($dataSet == "") || (strlen($dataSet) <= 2))) { // no action if no data
            include "crosswalk/defDetailTypesFields.inc";
            $query = "INSERT INTO `defDetailTypes` ($flds) VALUES" . $dataSet;
            mysql_query($query);
            if(mysql_error()) {
                echo "DETAILTYPES Error inserting data: " . mysql_error() . "<br />";
                $errorCreatingTables = TRUE;
            }
        } // END Imported first set of data to temp table: defDetailTypes
    } // processDetailTypes



    function processRecStructure($dataSet) {
        global $errorCreatingTables;
        if(!(($dataSet == "") || (strlen($dataSet) <= 2))) { // no action if no data
            include "crosswalk/defRecStructureFields.inc";
            $query = "INSERT INTO `defRecStructure` ($flds) VALUES " . $dataSet;
            mysql_query($query);
            if(mysql_error()) {
                echo "RECSTRUCTURE Error inserting data: " . mysql_error() . "<br />";
                $errorCreatingTables = TRUE;
            }
        } // END Imported first set of data to temp table: defRecStructure
    } // processRecStructure



    function processTerms($dataSet) {
        global $errorCreatingTables;
        if(!(($dataSet == "") || (strlen($dataSet) <= 2))) { // no action if no data
            include "crosswalk/defTermsFields.inc";
            $query = "SET FOREIGN_KEY_CHECKS = 0;";
            mysql_query($query);
            $query = "INSERT INTO `defTerms` ($flds) VALUES " . $dataSet;
            mysql_query($query);
            if(mysql_error()) {
                echo "TERMS Error inserting data: " . mysql_error() . "<br />";
                $errorCreatingTables = TRUE;
            }
            $query = "SET FOREIGN_KEY_CHECKS = 1;";
            mysql_query($query);
        } // END Imported first set of data to temp table: defTerms
    } // processTerms



    function processOntologies($dataSet) {
        global $errorCreatingTables;
        if(!(($dataSet == "") || (strlen($dataSet) <= 2))) { // no action if no data
            include "crosswalk/defOntologiesFields.inc";
            $query = "INSERT INTO `defOntologies` ($flds) VALUES " . $dataSet;
            mysql_query($query);
            if(mysql_error()) {
                echo "ONTOLOGIES Error inserting data: " . mysql_error() . "<br />";
                $errorCreatingTables = TRUE;
            }
        } // END Imported first set of data to temp table: defOntologies
    } // processOntologies



    function processRelationshipConstraints($dataSet) {
        global $errorCreatingTables;
        if(!(($dataSet == "") || (strlen($dataSet) <= 2))) { // no action if no data
            include "crosswalk/defRelationshipConstraintsFields.inc";
            $query = "INSERT INTO `defRelationshipConstraints` ($flds) VALUES " . $dataSet;
            mysql_query($query);
            if(mysql_error()) {
                echo "RELATIONSHIPCONSTRAINTS Error inserting data: " . mysql_error() . "<br />";
                $errorCreatingTables = TRUE;
            }
        } // END Imported first set of data to temp table: defRelationshipConstraints
    } // processRelationshipConstraints



    function processFileExtToMimetype($dataSet) {
        global $errorCreatingTables;
        if(!(($dataSet == "") || (strlen($dataSet) <= 2))) { // no action if no data
            include "crosswalk/defFileExtToMimetypeFields.inc";
            $query = "INSERT INTO `defFileExtToMimetype` ($flds) VALUES " . $dataSet;
            mysql_query($query);
            if(mysql_error()) {
                echo "FILEEXTTOMIMETYPE Error inserting data: " . mysql_error() . "<br />";
                $errorCreatingTables = TRUE;
            }
        } // END Imported first set of data to temp table: defFileExtToMimetype
    } //processFileExtToMimetype



    function processRecTypeGroups($dataSet) {
        global $errorCreatingTables;
        if(!(($dataSet == "") || (strlen($dataSet) <= 2))) { // no action if no data
            include "crosswalk/defRecTypeGroupsFields.inc";
            $query = "INSERT INTO `defRecTypeGroups` ($flds) VALUES " . $dataSet;
            mysql_query($query);
            if(mysql_error()) {
                echo "RECTYPEGROUPS Error inserting data: " . mysql_error() . "<br />";
                $errorCreatingTables = TRUE;
            }
        } // END Imported first set of data to temp table: defRecTypeGroups
    } // processRectypeGroups



    function processDetailTypeGroups($dataSet) {
        global $errorCreatingTables;
        if(!(($dataSet == "") || (strlen($dataSet) <= 2))) { // no action if no data
            include "crosswalk/defDetailTypeGroupsFields.inc";
            $query = "INSERT INTO `defDetailTypeGroups` ($flds) VALUES " . $dataSet;
            mysql_query($query);
            if(mysql_error()) {
                echo "DETAILTYPEGROUPS Error inserting data: " . mysql_error() . "<br /><br />" . $dataSet . "<br />";
                $errorCreatingTables = TRUE;
            }
        } // END Imported first set of data to temp table: defDetailTypeGroups
    } // processDetailTypeGroups



    function processTranslations($dataSet) {
        global $errorCreatingTables;
        if(!(($dataSet == "") || (strlen($dataSet) <= 2))) { // no action if no data
            include "crosswalk/defTranslationsFields.inc";
            $query = "INSERT INTO `defTranslations` ($flds) VALUES " . $dataSet;
            mysql_query($query);
            if(mysql_error()) {
                echo "TRANSLATIONS Error inserting data: " . mysql_error() . "<br />";
                $errorCreatingTables = TRUE;
            }
        } // END Imported first set of data to temp table: defTranslations
    } // processTranslations


    // Done inserting data into all tables in temp database (or actual database if new database).

    // If this spits out errors with unkonwn columns, look to see if createDefinitionsTablesOnly.sql has been brought
    // up to date with the structure of populateBlankDB.sql

    if($errorCreatingTables) { // An error occurred while trying to create one (or more) of the tables, or inserting data into them
        if($isNewDB) {
            echo "<br /><strong>An error occurred trying to insert data into the new database.</strong><br />";
        } else {
            echo "<br /><strong>An error occurred trying to insert the downloaded data into the temporary database.</strong><br />";
        }
        echo "This may be due to a database version mismatch, please advise the Heurist development team<br>";
        mysql_query("DROP DATABASE `" . $tempDBName . "`"); // Delete temp database or incomplete new database
        return;
    } else if(!$isNewDB){ // do crosswalking for exisitn database, no action for new database
        require_once("createCrosswalkTable.php"); // offer user choice of fields to import
//		mysql_query("DROP DATABASE `" . $tempDBName . "`");
    }

    // TODO: Replace this line with centralised locking methodology
    $res = mysql_query("delete from sysLocks where lck_ID='1'"); // Remove sysLock
?>
