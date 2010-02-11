<?php
# Lifter001: DONE
# Lifter002: TODO
# Lifter003: TEST
# Lifter007: TODO
# Lifter003: TODO
# Lifter005: TEST
/*
folder.php - Anzeige und Verwaltung des Ordnersystems
Copyright (C) 2001 Ralf Stockmann <rstockm@gwdg.de>, Cornelis Kater <ckater@gwdg.de>

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
*/

ob_start();
page_open(array("sess" => "Seminar_Session",
	"auth" => "Seminar_Auth", 
	"perm" => "Seminar_Perm", "" .
	"user" => "Seminar_User"));

include ('lib/seminar_open.php'); // initialise Stud.IP-Session

// -- here you have to put initialisations for the current page
require_once('lib/datei.inc.php');
require_once('lib/msg.inc.php');
require_once('lib/visual.inc.php');
require_once('config.inc.php');
require_once 'lib/functions.php';
require_once('lib/classes/StudipDocumentTree.class.php');
require_once 'lib/raumzeit/Issue.class.php';

$db = DBManager::get();
$db2 = DBManager::get();

//Switch fuer die Ansichten
URLHelper::bindLinkParam('data', $folder_system_data);
if ($_REQUEST['cmd'] == 'tree') {
	URLHelper::removeLinkParam('data');
	$folder_system_data = array();
	$folder_system_data['cmd'] = 'tree';
	URLHelper::addLinkParam('data', $folder_system_data);
} elseif ($_REQUEST['cmd'] == 'all') {
	URLHelper::removeLinkParam('data');
	$folder_system_data = array();
	$folder_system_data['cmd'] = 'all';
	URLHelper::addLinkParam('data', $folder_system_data);
} elseif(!isset($folder_system_data['cmd'])) {
	$folder_system_data['cmd'] = 'all';
}

if (strpos($open, "_") !== false) {
	$folder_system_data["open"][substr($open, 0, strpos($open, "_")+1)] = true;
}

if ($_REQUEST['orderby']) {
	$folder_system_data['orderby'] = $_REQUEST['orderby'];
} else {
	unset($folder_system_data['orderby']);
}

///////////////////////////////////////////////////////////
//Ajax-Funktionen
///////////////////////////////////////////////////////////

//Frage den Dateienk�rper ab
if ($_REQUEST["getfilebody"]) {
	//URLHelper::bindLinkParam('data', $folder_system_data);
	$folder_tree =& TreeAbstract::GetInstance('StudipDocumentTree', array('range_id' => $SessionSeminar));
	$result = $db->query("SELECT range_id FROM dokumente WHERE dokument_id = ".$db->quote($_REQUEST["getfilebody"]))->fetch();
	if ($folder_tree->isReadable($result['range_id'] , $user->id)) {
		$query = "SELECT ". $_fullname_sql['full'] ." AS fullname, username, a.user_id, a.*, IF(IFNULL(a.name,'')='', a.filename,a.name) AS t_name FROM dokumente a LEFT JOIN auth_user_md5 USING (user_id) LEFT JOIN user_info USING (user_id) WHERE a.dokument_id = ".$db->quote($_REQUEST["getfilebody"])."";
		$datei = $db->query($query)->fetch();
		ob_start();
		display_file_body($datei, $folder_system_data["open"], $change, $folder_system_data["move"], $folder_system_data["upload"], FALSE, $folder_system_data["refresh"], $folder_system_data["link"], NULL);
		$output = ob_get_clean();
		print utf8_encode($output);
	}
	die();
}

//Frage den Ordnerk�rper ab
if ($_REQUEST["getfolderbody"]) {
	//URLHelper::bindLinkParam('data', $folder_system_data);
	$folder_tree =& TreeAbstract::GetInstance('StudipDocumentTree', array('range_id' => $SessionSeminar));
	if ($folder_tree->isExecutable($_REQUEST["getfolderbody"] , $user->id)) {
		ob_start();
		display_folder_body($_REQUEST["getfolderbody"], $folder_system_data["open"], $change, $move, $upload, $refresh, $filelink, NULL);
		$output = ob_get_clean();
		print utf8_encode($output);
	}
	die();
}

//Dateien eines Ordners sollen sortiert werden nach einem Array
if ($_REQUEST["folder_sort"]) {
	ob_start();
	URLHelper::bindLinkParam('data', $folder_system_data);
	$folder_tree =& TreeAbstract::GetInstance('StudipDocumentTree', array('range_id' => $SessionSeminar));
	if (($rechte) && ($_REQUEST["folder_sort"] == "root")) {
		
	} else {
		if (($rechte) || ($folder_tree->isWriteable($_REQUEST["folder_sort"] , $user->id))) {
			$file_order = explode(",", $file_order);
			$sorttype = "";
			if ($file_order) {
				$result = $db->query("SELECT 1 FROM dokumente WHERE dokument_id = ".$db->quote($file_order[0]))->fetch();
				if ($result) {
					$sorttype = "file";
				} else {
					$result = $db->query("SELECT 1 FROM folder WHERE folder_id = ".$db->quote($file_order[0]))->fetch();
					if ($result) {
						$sorttype = "folder";
					}
				}
			}
			if ($sorttype == "file") {
				//Dateien werden sortiert:
				for ($i=0; $i < count($file_order); $i++) {
					$db->query("UPDATE dokumente SET priority = ".($i+1)." WHERE dokument_id = ".$db->quote($file_order[$i]));
				}
			} elseif ($sorttype == "folder") {
				//Ordner werden sortiert:
				for ($i=0; $i < count($file_order); $i++) {
					$db->query("UPDATE folder SET priority = ".($i+1)." WHERE folder_id = ".$db->quote($file_order[$i]));
				}
			}
		}
	}
	$output = ob_get_clean();
	print utf8_encode($output);
	die();
}

//Datei soll in einen Ordner verschoben werden
if (($_REQUEST["moveintofolder"]) && ($_REQUEST["movefile"])) {
	URLHelper::bindLinkParam('data', $folder_system_data);
	$folder_tree =& TreeAbstract::GetInstance('StudipDocumentTree', array('range_id' => $SessionSeminar));
	$result = $db->query("SELECT range_id FROM dokumente WHERE dokument_id = '".$_REQUEST["movefile"]."'")->fetch();
	if (($rechte) || (($folder_tree->isWriteable($result['range_id'] , $user->id)) 
		 && ($folder_tree->isWriteable($result['moveintofolder'] , $user->id)))) {
		$db->query("UPDATE dokumente SET range_id = '".$_REQUEST["moveintofolder"]."', priority = 0 WHERE dokument_id = '".$_REQUEST["movefile"]."'");
	}
	die();
}

//Datei soll in einen Ordner kopiert werden
if (($_REQUEST["copyintofolder"]) && ($_REQUEST["copyfile"])) {
	URLHelper::bindLinkParam('data', $folder_system_data);
	$folder_tree =& TreeAbstract::GetInstance('StudipDocumentTree', array('range_id' => $SessionSeminar));
	$result = $db->query("SELECT * FROM dokumente WHERE dokument_id = ".$db->quote($_REQUEST["copyfile"]))->fetch();
	if (($rechte) || ($folder_tree->isWriteable($result['moveintofolder'] , $user->id))) {
		$db->query("INSERT INTO dokumente " .
				"SET dokument_id = '".md5(uniqid("helloGOOdByE"))."', " .
						"range_id = ".$db->quote($_REQUEST["copyintofolder"]).", " .
						"user_id = ".$db->quote($user->id).", " .
						"seminar_id = ".$db->quote($SessionSeminar).", " .
						"name = ".$db->quote($result['name']).", " .
						"description = ".$db->quote($result['description']).", " .
						"filename = ".$db->quote($result['filename']).", " .
						"mkdate = ".$db->quote($result['mkdate']).", " .
						"chdate = ".$db->quote(time()).", " .
						"filesize = ".$db->quote($result['filesize']).", " .
						"autor_host = ".$db->quote($result['autor_host']).", " .
						"downloads = ".$db->quote(0).", " .
						"url = ".$db->quote($result['url']).", " .
						"protected = ".$db->quote($result['protected']).", " .
						"priority = '0'");
	}
	die();
}

if ($_REQUEST['folderzip']) {
	$zip_file_id = createFolderZip($_REQUEST['folderzip'], true, true);
	if($zip_file_id){
		$query = sprintf ("SELECT name FROM folder WHERE folder_id = '%s'", $_REQUEST['folderzip']);
		$result = $db->query($query)->fetch();
		$zip_name = prepareFilename(_("Dateiordner").'_'.$result['name'].'.zip');
		header('Location: ' . getDownloadLink( $zip_file_id, $zip_name, 4));
		page_close();
		die;
	}
}

if ($_REQUEST['zipnewest']) {
	//Abfrage der neuen Dateien
	$folder_tree =& TreeAbstract::GetInstance('StudipDocumentTree', array('range_id' => $SessionSeminar));
	$download_ids = $db->query("SELECT * " .
			"FROM dokumente " .
			"WHERE seminar_id = '$SessionSeminar' " .
			"AND user_id != '".$user->id."' " .
			"AND ( chdate > '".(($_REQUEST['zipnewest']) ? $_REQUEST['zipnewest'] : time())."' " .
					"OR mkdate > '".(($_REQUEST['zipnewest']) ? $_REQUEST['zipnewest'] : time())."')")->fetchAll();
	foreach($download_ids as $key => $dl_id) {
		if ($folder_tree->isReadable($dl_id['range_id'], $user->id) 
		    && check_protected_download($dl_id['dokument_id']) && $dl_id['url'] == "") {
			$download_ids[$key] = $dl_id['dokument_id'];
		} else {
			unset($download_ids[$key]);
		}
	}
	if (count($download_ids)>0) {
		$zip_file_id = createSelectedZip($download_ids, true, true);
		if($zip_file_id){
			$zip_name = prepareFilename($SessSemName[0].'-'._("Neue Dokumente").'.zip');
			header('Location: ' . getDownloadLink( $zip_file_id, $zip_name, 4));
			page_close();
			die;
		}
	}
}

if ($download_selected_x) {
	if (is_array($download_ids)) {
		$zip_file_id = createSelectedZip($download_ids, true, true);
		if($zip_file_id){
			$zip_name = prepareFilename($SessSemName[0].'-'._("Dokumente").'.zip');
			header('Location: ' . getDownloadLink( $zip_file_id, $zip_name, 4));
			page_close();
			die;
		}
	}
}

if($zip_file_id === false){
	$msg = 'error�'
	. sprintf(_("Der Zip Download ist fehlgeschlagen. Bitte beachten Sie das Limit " 
	.	"von maximal %s Dateien und die maximale Gr��e der zu zippenden Dateien von %s MB."),
	(int)Config::GetInstance()->getValue('ZIP_DOWNLOAD_MAX_FILES'),
	(int)Config::GetInstance()->getValue('ZIP_DOWNLOAD_MAX_SIZE') )
	. '�';
}

//mark_public_course();

// Start of Output

$HELP_KEYWORD="Basis.Dateien";
$CURRENT_PAGE = $SessSemName["header_line"]. " - " . _("Dateien");

if ($folder_system_data['cmd'] == 'all') {
	Navigation::activateItem('/course/files/all');
} else {
	Navigation::activateItem('/course/files/tree');
}

include ('lib/include/html_head.inc.php'); // Output of html head
include ('lib/include/header.php');   // Output of Stud.IP head

checkObject();
checkObjectModule('documents');
object_set_visit_module('documents');


$folder_tree =& TreeAbstract::GetInstance('StudipDocumentTree', array('range_id' => $SessionSeminar));

//include ('lib/include/links_openobject.inc.php');
//Nur bei 1.8 - irgendwie haut das links_openobject .inc.php das $db raus
$db = DBManager::get();


//obskuren id+_?_ string zerpfl�cken
if (strpos($open, "_") !== false){
	list($open_id, $open_cmd) = explode('_', $open);
}

//Wenn nicht Rechte und Operation uebermittelt: Ist das mein Dokument und ist der Ordner beschreibbar?
if ((!$rechte) && $open_cmd) {
	$query = "SELECT user_id,range_id FROM dokumente WHERE dokument_id = ".$db->quote($open_id)."";
	$result = $db->query($query)->fetch();
	if (($result["user_id"] == $user->id) 
		 && ($result["user_id"] != "nobody") 
		 && $folder_tree->isWritable($result['range_id'], $user->id))
		$owner=TRUE;
	else
		$owner=FALSE;
} else
	$owner=FALSE;
if(!$rechte && in_array($open_cmd, array('n','d','c','sc','m','co')) && $SemUserStatus == "autor"){
	$create_folder_perm = $folder_tree->checkCreateFolder($open_id, $user->id);
} else {
	$create_folder_perm = false;
}
//verschiebemodus abbrechen, wenn andere Aktion ausgew�hlt wurde
if($folder_system_data["mode"] != '' && ($open_cmd && !in_array($open_cmd, array('n','md')))){
	$folder_system_data["move"]='';
	$folder_system_data["mode"]='';
}

if ($rechte || $owner || $create_folder_perm) {
	//wurde Code fuer Anlegen von Ordnern ubermittelt (=id+"_n_"), wird entsprechende Funktion aufgerufen
	if ($open_cmd == 'n' && (!$cancel_x)) {
		$change = create_folder(_("Neuer Ordner"), '', $open_id );
		$open_id = $change;
		//$open_cmd = null;
		}

	//wurde Code fuer Anlegen von Ordnern der obersten Ebene ubermittelt (=id+"_a_"), 
	//wird entsprechende Funktion aufgerufen
	if ($open_cmd == 'a') {
		$permission = 7;
		if ($open_id == $SessionSeminar) {
			$titel=_("Allgemeiner Dateiordner");
			$description= sprintf(_("Ablage f�r allgemeine Ordner und Dokumente der %s"), $SessSemName["art_generic"]);
		} else if ($open_id == md5('new_top_folder')){
			$titel = $_REQUEST['top_folder_name'] ? stripslashes($_REQUEST['top_folder_name']) : _("Neuer Ordner");
			$open_id = md5($SessionSeminar . 'top_folder');
		} elseif($titel = GetStatusgruppeName($open_id)) {
			$titel = _("Dateiordner der Gruppe:") . ' ' . $titel;
			$description = _("Ablage f�r Ordner und Dokumente dieser Gruppe");
			$permission = 15;
		} else if ($data = SingleDateDB::restoreSingleDate($open_id)) {
			// If we create a folder which has not yet an issue, we just create one
			$issue = new Issue(array('seminar_id' => $SessionSeminar));
			$issue->setTitle(_("Ohne Titel"));
			$termin = new SingleDate($open_id);
			$termin->addIssueID($issue->getIssueID());
			$issue->store();
			$termin->store();

			$open_id = $issue->getIssueID();
			$titel = $issue->getTitle();
			$description= _("Themenbezogener Dateiordner");
		} else {
			$query = "SELECT title FROM themen WHERE issue_id=".$db->quote($open_id)."";
			if ($result = $db->query($query)->fetch()) {
				$titel = $result["title"];
				$description= _("Themenbezogener Dateiordner");
			}
		}
		$change = create_folder(addslashes($titel), $description, $open_id, $permission);
		$folder_system_data["open"][$change] = TRUE;
		$folder_system_data['open']['anker'] = $change;
	}

	//wurde Code fuer Loeschen von Ordnern ubermittelt (=id+"_d_"), wird entsprechende Funktion aufgerufen
	if ($open_cmd == 'd') {
		if ( ($count = doc_count($open_id)) ){
			$msg="info�" . sprintf(_("Der ausgew�hlte Ordner enth�lt %s Datei(en). Wollen Sie den Ordner wirklich l�schen?"), $count) . "<br>";
			$msg.="<b><a href=\"".URLHelper::getLink("?open=".$open_id."_rd_")."\">" . makeButton("ja2", "img") . "</a>&nbsp;&nbsp; <a href=\"".URLHelper::getLink('')."\">" . makeButton("nein", "img") . "</a>�";
		} else {
			delete_folder($open_id, true);
			$open_id = $folder_tree->getParents($open_id); 
			$open_id = $open_id[0]; 
			$folder_tree->init();
		}
	}

	//Loeschen von Ordnern im wirklich-ernst Mode
	if ($open_cmd == 'rd') {
		delete_folder($open_id, true);
		$open_id = $folder_tree->getParents($open_id); 
		$open_id = $open_id[0]; 
		$folder_tree->init();
	}

	//wurde Code fuer Loeschen von Dateien ubermittelt (=id+"_fd_"), wird erstmal nachgefragt
	if ($open_cmd == 'fd') {
		$query = "SELECT filename, ". $_fullname_sql['full'] ." AS fullname, username FROM dokumente LEFT JOIN auth_user_md5 USING (user_id) LEFT JOIN user_info USING (user_id) WHERE dokument_id ='".$open_id."'";
		$result = $db->query($query)->fetch();
		if (getLinkPath($open_id)) {
			$msg="info�" . sprintf(_("Wollen Sie die Verlinkung zu <b>%s</b> von %s wirklich l�schen?"), htmlReady($result["filename"]), "<a href=\"".URLHelper::getLink("about.php?username=".$result["username"])."\">".htmlReady($result["fullname"])."</a>") . "<br>";
			$msg.="<b><a href=\"".URLHelper::getLink("?open=".$open_id."_rl_")."\">" . makeButton("ja2", "img") . "</a>&nbsp;&nbsp; <a href=\"".URLHelper::getLink('')."\">" . makeButton("nein", "img") . "</a>�";
		} else {
			$msg="info�" . sprintf(_("Wollen Sie die Datei <b>%s</b> von %s wirklich l�schen?"), htmlReady($result["filename"]), "<a href=\"".URLHelper::getLink("about.php?username=".$result["username"])."\">".htmlReady($result["fullname"])."</a>") . "<br>";
			$msg.="<b><a href=\"".URLHelper::getLink("?open=".$open_id."_rm_")."\">" . makeButton("ja2", "img") . "</a>&nbsp;&nbsp; <a href=\"".URLHelper::getLink('')."\">" . makeButton("nein", "img") . "</a>�";
		}
	}

	//Loeschen von Dateien im wirklich-ernst Mode
	if ($open_cmd == 'rm') {
		if (delete_document($open_id))
			$msg.="msg�" . _("Die Datei wurde gel&ouml;scht") . "�";
		else
			$msg.="error�" . _("Die Datei konnte nicht gel&ouml;scht werden") . "�";
		}

	//Loeschen von verlinkten Dateien im wirklich-ernst Mode
	if ($open_cmd == 'rl') {
		if (delete_link($open_id))
			$msg.="msg�" . _("Die Verlinkung wurde gel�scht") . "�";
		else
			$msg.="error�" . _("Die Verlinkung konnte nicht gel�scht werden") . "�";
		}

	//wurde Code fuer Aendern des Namens und der Beschreibung von Ordnern oder Dokumenten ubermittelt (=id+"_c_"), wird entsprechende Funktion aufgerufen
	if ($open_cmd ==  'c') {
		$change=$open_id;
		}

	//wurde Code fuer Speichern von Aenderungen uebermittelt (=id+"_sc_"), wird entsprechende Funktion aufgerufen
	if ($open_cmd == 'sc' && (!$cancel_x)) {
		edit_item($open_id, $type, $change_name, $change_description, $change_protected);
		}

	//wurde Code fuer Verschieben-Vorwaehlen uebermittelt (=id+"_m_"), wird entsprechende Funktion aufgerufen
	if ($open_cmd == 'm' && (!$cancel_x)) {
		$folder_system_data["move"]=$open_id;
		$folder_system_data["mode"]='move';
		}

	//wurde Code fuer Hoch-Schieben einer Datei (=id+"_mfu_") in der Darstellungsreihenfolge ausgew�hlt?
	if (($open_cmd == 'mfu') && (!$cancel_x)) {
		$result = $db->query("SELECT range_id FROM dokumente WHERE dokument_id = ".$db->quote($open_id)."")->fetch();
		$result = $db->query("SELECT dokument_id FROM dokumente WHERE range_id = '".$result['range_id']."' ORDER BY priority ASC, chdate")->fetchAll();
		for ($i=1; $i < count($result); $i++) {
			if ($result[$i]['dokument_id'] == $open_id) {
				$result[$i]['dokument_id'] = $result[$i-1]['dokument_id'];
				$result[$i-1]['dokument_id'] = $open_id;
			}
		}
		for ($i=0; $i < count($result); $i++) {
			$db->query("UPDATE dokumente SET priority = ".($i+1)." WHERE dokument_id = '".$result[$i]['dokument_id']."'");
		}
		unset($open_id);
	}

	//wurde Code fuer Runter-Schieben einer Datei (=id+"_mfu_") in der Darstellungsreihenfolge ausgew�hlt?
	if (($open_cmd == 'mfd') && (!$cancel_x)) {
		$result = $db->query("SELECT range_id FROM dokumente WHERE dokument_id = ".$db->quote($open_id)."")->fetch();
		$result = $db->query("SELECT dokument_id FROM dokumente WHERE range_id = '".$result['range_id']."' ORDER BY priority ASC, chdate")->fetchAll();
		for ($i=count($result)-1; $i >=0 ; $i--) {
			if ($result[$i]['dokument_id'] == $open_id) {
				$result[$i]['dokument_id'] = $result[$i+1]['dokument_id'];
				$result[$i+1]['dokument_id'] = $open_id;
			}
		}
		for ($i=0; $i < count($result); $i++) {
			$db->query("UPDATE dokumente SET priority = ".($i+1)." WHERE dokument_id = '".$result[$i]['dokument_id']."'");
		}
		unset($open_id);
	}
	
	//wurde Code fuer Hoch-Schieben eines Ordners (=id+"_mfou_") in der Darstellungsreihenfolge ausgew�hlt?
	if (($open_cmd == 'mfou') && (!$cancel_x)) {
		$result = $db->query("SELECT range_id FROM folder WHERE folder_id = ".$db->quote($open_id))->fetch();
		$result = $db->query("SELECT folder_id FROM folder WHERE range_id = '".$result['range_id']."' ORDER BY priority ASC, chdate")->fetchAll();
		for ($i=1; $i < count($result); $i++) {
			if ($result[$i]['folder_id'] == $open_id) {
				$result[$i]['folder_id'] = $result[$i-1]['folder_id'];
				$result[$i-1]['folder_id'] = $open_id;
			}
		}
		for ($i=0; $i < count($result); $i++) {
			$db->query("UPDATE folder SET priority = ".($i+1)." WHERE folder_id = '".$result[$i]['folder_id']."'");
		}
		unset($open_id);
	}

	//wurde Code fuer Runter-Schieben einer Datei (=id+"_mfu_") in der Darstellungsreihenfolge ausgew�hlt?
	if (($open_cmd == 'mfod') && (!$cancel_x)) {
		$result = $db->query("SELECT range_id FROM folder WHERE folder_id = ".$db->quote($open_id))->fetch();
		$result = $db->query("SELECT folder_id FROM folder WHERE range_id = '".$result['range_id']."' ORDER BY priority ASC, chdate")->fetchAll();
		for ($i=count($result)-1; $i >=0 ; $i--) {
			if ($result[$i]['folder_id'] == $open_id) {
				$result[$i]['folder_id'] = $result[$i+1]['folder_id'];
				$result[$i+1]['folder_id'] = $open_id;
			}
		}
		for ($i=0; $i < count($result); $i++) {
			$db->query("UPDATE folder SET priority = ".($i+1)." WHERE folder_id = '".$result[$i]['folder_id']."'");
		}
		unset($open_id);
	}
	
	//wurde Code f�r alphabetisches Sortieren (=id+"_az_") fuer Ordner id ausgew�hlt?
	if (($open_cmd == 'az') && (!$cancel_x)) {
		$result = $db->query("SELECT dokument_id FROM dokumente WHERE range_id = ".$db->quote($open_id)." ORDER BY name ASC, chdate DESC")->fetchAll();
		for ($i=0; $i < count($result); $i++) {
			$db->query("UPDATE dokumente SET priority = ".($i+1)." WHERE dokument_id = '".$result[$i]['dokument_id']."'");
		}
		$result = $db->query("SELECT folder_id FROM folder WHERE range_id = ".$db->quote($open_id)." ORDER BY name ASC, chdate DESC")->fetchAll();
		for ($i=0; $i < count($result); $i++) {
			$db->query("UPDATE folder SET priority = ".($i+1)." WHERE folder_id = '".$result[$i]['folder_id']."'");
		}
	}
	
	//wurde Code fuer Kopieren-Vorwaehlen uebermittelt (=id+"_co_"), wird entsprechende Funktion aufgerufen
	if ($open_cmd == 'co' && (!$cancel_x)) {
		$folder_system_data["move"]=$open_id;
		$folder_system_data["mode"]='copy';
		}

	//wurde Code fuer Aktualisieren-Hochladen uebermittelt (=id+"_rfu_"), wird entsprechende Variable gesetzt
	if ($open_cmd == 'rfu' && (!$cancel_x)) {
		$folder_system_data["upload"]=$open_id;
		$folder_system_data["refresh"]=$open_id;
		unset($folder_system_data["zipupload"]);
	}

	//wurde Code fuer Aktualisieren-Verlinken uebermittelt (=id+"_led_"), wird entsprechende Variable gesetzt
	if ($open_cmd == 'led' && (!$cancel_x)) {
		$folder_system_data["link"]=$open_id;
		$folder_system_data["update_link"]=TRUE;
	}
}

//Upload, Check auf Konsistenz mit Seminar-Schreibberechtigung
if (($SemUserStatus == "autor") || ($rechte)) {
	//wurde Code fuer Hochladen uebermittelt (=id+"_u_"), wird entsprechende Variable gesetzt
	if ($open_cmd == 'u' && (!$cancel_x)) {
		$folder_system_data["upload"]=$open_id;
		unset($folder_system_data["zipupload"]);
	}
	if ($open_cmd == 'z' && $rechte  && !$cancel_x) {
		$folder_system_data["upload"]=$open_id;
		$folder_system_data["zipupload"]=$open_id;
	}


	//wurde Code fuer Verlinken uebermittelt (=id+"_l_"), wird entsprechende Variable gesetzt
	if ($open_cmd == 'l' && (!$cancel_x)) {
		$folder_system_data["link"]=$open_id;
	}

	//wurde eine Datei hochgeladen/aktualisiert?
	if (($cmd=="upload") && (!$cancel_x) && ($folder_system_data["upload"])) {
		if (!$folder_system_data["zipupload"]){
			upload_item ($folder_system_data["upload"], TRUE, FALSE, $folder_system_data["refresh"]);
			$open = $dokument_id;
			$close = $folder_system_data["refresh"];
			$folder_system_data["upload"]='';
			$folder_system_data["refresh"]='';
		} elseif ($rechte && get_config('ZIP_UPLOAD_ENABLE')) {
			upload_zip_item();
			$folder_system_data["upload"]='';
			$folder_system_data["zipupload"]='';
		}
		unset($cmd);
		}

	//wurde eine Datei verlinkt?
	if (($cmd=="link") && (!$cancel_x) && ($folder_system_data["link"])) {
		if (link_item ($folder_system_data["link"], TRUE, FALSE, $folder_system_data["refresh"],FALSE)) {
			$open = $dokument_id;
			$close = $folder_system_data["refresh"];
			$folder_system_data["link"]='';
			$folder_system_data["refresh"]='';
			$folder_system_data["update_link"]='';
			unset($cmd);
		} else {
			$folder_system_data["linkerror"]=TRUE;
		}
	}

	//wurde ein Link aktualisiert?
	if (($cmd=="link_update") && (!$cancel_x) && ($folder_system_data["link"])) {
		if (link_item ($range_id, TRUE, FALSE, FALSE, $link_update)) {
			$open = $link_update;
			$close = $folder_system_data["refresh"];
			$folder_system_data["link"]='';
			$folder_system_data["refresh"]='';
			$folder_system_data["update_link"]='';
			unset($cmd);
		} else {
			$folder_system_data["linkerror"]=TRUE;
		}
	}
	//verschieben / kopieren in andere Veranstaltung
	if ($rechte && ($_POST['move_to_sem_x'] || $_POST['move_to_inst_x'] || $_POST['move_to_top_folder_x'])){
		if(!$_POST['move_to_top_folder_x']){
			$new_sem_id = ($_POST['move_to_sem_x'] ? $_POST['sem_move_id'] : $_POST['inst_move_id']);
		} else {
			$new_sem_id = false;
		}
		if($new_sem_id) $new_range_id = md5($new_sem_id . 'top_folder');
		else $new_range_id = md5($SessSemName[1] . 'top_folder');
		if ($new_range_id){
			if ($folder_system_data["mode"] == 'move'){
				$done = move_item($folder_system_data["move"], $new_range_id, $new_sem_id);
				if (!$done){
					$msg .= "error�" . _("Verschiebung konnte nicht durchgef�hrt werden. Eventuell wurde im Ziel der Allgemeine Dateiordner nicht angelegt.") . "�";
				} else {
					$msg .= "msg�" . sprintf(_("%s Ordner, %s Datei(en) wurden verschoben."), $done[0], $done[1]) . '�';
				}
			} else {
				$done = copy_item($folder_system_data["move"], $new_range_id, $new_sem_id);
				if (!$done){
					$msg .= "error�" . _("Kopieren konnte nicht durchgef�hrt werden. Eventuell wurde im Ziel der Allgemeine Dateiordner nicht angelegt.") . "�";
				} else {
					$msg .= "msg�" . sprintf(_("%s Ordner, %s Datei(en) wurden kopiert."), $done[0], $done[1]) . '�';
				}
			}
		}
		$folder_system_data["move"]='';
		$folder_system_data["mode"]='';
	}

	if ($cancel_x)  {
		$folder_system_data["upload"]='';
		$folder_system_data["refresh"]='';
		$folder_system_data["link"]='';
		$folder_system_data["update_link"]='';
		$folder_system_data["move"]='';
		$folder_system_data["mode"]='';
		$folder_system_data["zipupload"]='';
		unset($cmd);
	}
}

//verschieben / kopieren innerhalb der Veranstaltung
//wurde Code fuer Starten der Verschiebung uebermittelt (=id+"_md_"), wird entsprechende Funktion aufgerufen (hier kein Rechtecheck noetig, da Dok_id aus Sess_Variable.
if ($open_cmd == 'md' && $folder_tree->isWritable($open_id, $user->id) && !$cancel_x && (!$folder_tree->isFolder($folder_system_data["move"]) || ($folder_tree->isFolder($folder_system_data["move"]) && $folder_tree->checkCreateFolder($open_id, $user->id)))) {
	if ($folder_system_data["mode"] == 'move'){
		$done = move_item($folder_system_data["move"], $open_id);
		if (!$done){
			$msg .= "error�" . _("Verschiebung konnte nicht durchgef�hrt werden.") . "�";
		} else {
			$msg .= "msg�" . sprintf(_("%s Ordner, %s Datei(en) wurden verschoben."), $done[0], $done[1]) . '�';
		}
	} else {
		$done = copy_item($folder_system_data["move"], $open_id);
		if (!$done){
			$msg .= "error�" . _("Kopieren konnte nicht durchgef�hrt werden.") . "�";
		} else {
			$msg .= "msg�" . sprintf(_("%s Ordner, %s Datei(en) wurden kopiert."), $done[0], $done[1]) . '�';
		}
	}
	$folder_system_data["move"]='';
	$folder_system_data["mode"]='';
}

//wurde ein weiteres Objekt aufgeklappt?
if (isset($open)) {
	if (!isset($open_id))
		$open_id = $open;
	$folder_system_data["open"][$open_id] = true;
	$folder_system_data["open"]['anker'] = $open_id;
	//�bergeordnete Ordner mit�ffnen - das ergibt Sinn
	if (!($path = $folder_tree->getParents($open_id))) {
		//Und falls $open ein Dokument sein sollte:
		$path = $db->query("SELECT range_id FROM dokumente WHERE dokument_id = '".$open_id."'")->fetch();
		$path = $path["range_id"];
		$folder_system_data["open"][$path] = true;
		$path = $folder_tree->getParents($path);
	}
	for ($i=0; $i < count($path); $i++) {
		if ($path[$i] != "root") 
			$folder_system_data["open"][$path[$i]] = true;
	}
}
//wurde ein Objekt zugeklappt?
if ($close) {
	unset($folder_system_data["open"][$close]);
	$folder_system_data["open"]['anker'] = $close;
}


// Hauptteil

 if (!isset($range_id))
 	$range_id = $SessionSeminar ;

//JS Routinen einbinden, wenn benoetigt. Wird in der Funktion gecheckt, ob noetig...
JS_for_upload();
//we need this <body> tag, sad but true :)
echo "\n<body onUnLoad=\"upload_end()\">";
?>
<table cellspacing="0" cellpadding="0" border="0" width="100%">

<?
		if ($msg) {
		 echo "<tr><td class='blank' colspan=3>&nbsp;";
		 parse_msg($msg);
		 echo "</td></tr>";
		}

	//Ordner die fehlen, anlegen: Allgemeiner, wenn nicht da, Ordner zu Terminen, die keinen Ordner haben
	if ($rechte){
		if ($folder_system_data['mode']){
			$module_check = new Modules();
			$my_sem = $my_inst = array();
			foreach(search_range('%') as $key => $value){
				if ($module_check->getStatus('documents', $key, $value['type']) && $key != $SessionSeminar){
					if ($value['type'] == 'sem'){
						$my_sem[$key] = $value['name'];
					} else {
						$my_inst[$key] = $value['name'];
					}
				}
			}
			asort($my_sem, SORT_STRING);
			asort($my_inst, SORT_STRING);
			$button_name = ($folder_system_data["mode"] == 'move' ? 'verschieben' : 'kopieren');
			echo '<form action="'.URLHelper::getLink('').'" method="post">';
			echo "\n" . '<tr><td class="blank" colspan="3" width="100%" style="font-size:80%;">';
			echo "\n" . '<div style="margin-left:25px;">';
			echo "\n<b>" . ($folder_system_data["mode"] == 'move' ? _("Verschiebemodus") : _("Kopiermodus")) . "</b><br>";
			if(!$folder_tree->isFolder($folder_system_data["move"])){
				echo _("Ausgew�hlte Datei in den Allgemeinen Dateiordner einer anderen Veranstaltung oder einer anderen Einrichtung verschieben / kopieren:");
			} else {
				echo _("Ausgew�hlten Ordner in eine andere Veranstaltung, eine andere Einrichtung oder auf die obere Ebene verschieben / kopieren:");
			}
			echo "\n</div></td></tr><tr>";
			if($folder_tree->isFolder($folder_system_data["move"])){
				echo "\n" . '<td class="blank">&nbsp;</td>';
				echo "\n" . '<td class="blank" width="60%" style="font-size:80%;">';
				echo "\n" . '<input type="image" border="0" src="'.$GLOBALS['ASSETS_URL'].'images/move.gif" name="move_to_top_folder" ' . tooltip(_("Auf die obere Ebene verschieben / kopieren")) . '>';
				echo '&nbsp;' . _("Auf die obere Ebene verschieben / kopieren") . '</td>';
				echo "\n" . '<td class="blank"><input type="image" border="0" vspace="2" ' . makeButton($button_name,'src') . ' name="move_to_top_folder" ' . tooltip(_("Auf die obere Ebene verschieben / kopieren")) . '>';
				echo "\n</td></tr><tr>";
			}
			echo "\n" .'<td class="blank" width="20%" style="font-size:80%;">';
			echo "\n" . '<div style="margin-left:25px;">';
			echo _("Veranstaltung") .':';
			echo '</div></td><td class="blank" width="60%">';
			echo "\n" . '<input type="image" border="0" src="'.$GLOBALS['ASSETS_URL'].'images/move.gif" name="move_to_sem" ' . tooltip(_("In diese Veranstaltung verschieben / kopieren")) . '>';
			echo "\n" . '<select name="sem_move_id" style="width:90%">';
			foreach ($my_sem as $id => $name){
				echo "\n" . '<option value="'.$id.'">' . htmlReady(my_substr($name,0,70)) . '</option>';
			}
			echo "\n" . '</select>';
			echo "\n</td>";
			echo "\n" . '<td class="blank"><input type="image" border="0" vspace="2"' . makeButton($button_name,'src') . ' name="move_to_sem" ' . tooltip(_("In diese Veranstaltung verschieben / kopieren")) . '>';

			echo "\n</td></tr><tr>";
			echo "\n" .'<td class="blank" width="20%"  style="font-size:80%;">';
			echo "\n" . '<div style="margin-left:25px;">';
			echo _("Einrichtung").':';
			echo '</div></td><td class="blank" width="60%">';
			echo "\n" . '<input type="image" border="0" src="'.$GLOBALS['ASSETS_URL'].'images/move.gif" name="move_to_inst" ' . tooltip(_("In diese Einrichtung verschieben / kopieren")) . '>';
			echo "\n" . '<select name="inst_move_id" style="width:90%">';
			foreach ($my_inst as $id => $name){
				echo "\n" . '<option value="'.$id.'">' . htmlReady(my_substr($name,0,70)) . '</option>';
			}
			echo "\n" . '</select>';
			echo "\n</td>";
			echo "\n" . '<td class="blank"><input type="image" border="0" vspace="2" ' . makeButton($button_name,'src') . ' name="move_to_inst" ' . tooltip(_("In diese Einrichtung verschieben / kopieren")) . '>';

			echo "\n</td></tr><tr>";
			echo "\n" . '<td class="blank" align="center" colspan="3" width="100%" >';
			echo "\n" . '<input type="image" border="0" vspace="2" '.makeButton("abbrechen", "src").' name="cancel" ' . tooltip(_("Verschieben / Kopieren abbrechen")) . '>';
			echo "\n" . '</td></tr></form>';


		} elseif($folder_system_data["cmd"]=="tree") {
			$select = '<option value="' . md5("new_top_folder") . '_a_">' . _("ausw&auml;hlen oder wie Eingabe").' --&gt;</option>';
			$query = "SELECT SUM(1) FROM folder WHERE range_id='$range_id'";
			$result2 = $db2->query($query)->fetch();
			if ($result2[0] == 0)
				$select.="\n<option value=\"".$range_id."_a_\">" . _("Allgemeiner Dateiordner") . "</option>";

			
			if($SessSemName['class'] == 'sem'){
				$query = "SELECT statusgruppen.name, statusgruppe_id FROM statusgruppen LEFT JOIN folder ON (statusgruppe_id = folder.range_id) WHERE statusgruppen.range_id='$range_id' AND folder_id IS NULL ORDER BY position";
				$result2 = $db2->query($query)->fetchAll();
				foreach ($result2 as $row2) {
					$select.="\n<option value=\"".$row2["statusgruppe_id"]."_a_\">" . sprintf(_("Dateiordner der Gruppe: %s"), htmlReady($row2['name'])) . "</option>";
				}

				$query = "SELECT themen_termine.issue_id, termine.date, folder.name, termine.termin_id, date_typ FROM termine LEFT JOIN themen_termine USING (termin_id) LEFT JOIN folder ON (themen_termine.issue_id = folder.range_id) WHERE termine.range_id='$range_id' AND folder.folder_id IS NULL ORDER BY termine.date, name";
				
				$issues = array();
				$shown_dates = array();
				$result2 = $db2->query($query)->fetchAll();
				
				foreach ($result2 as $row2) {
					if (!$row2["name"]) {
						$issue_name = false;
						if ($row2['issue_id']) {
							if (!$issues[$row2['issue_id']]) {
								$issues[$row2['issue_id']] = new Issue(array('issue_id' => $row2['issue_id']));
							}
							$issue_name = $issues[$row2['issue_id']]->toString();
							$issue_name = htmlReady(my_substr($issue_name, 0, 20));
							$option_id = $row2['issue_id'];
						} else {
							$option_id = $row2['termin_id'];
						}

						$select .= "\n".sprintf('<option value="%s_a_">%s</option>',
							$option_id,
							sprintf(_("Ordner f�r %s [%s]%s"),
								date("d.m.Y", $row2["date"]),
								$TERMIN_TYP[$row2["date_typ"]]["name"],
								($issue_name ? ', '.$issue_name : '')
							)
						);

					}
				}

			}

			if ($select) {
				?>
				<tr>
				<td class="blank" colspan="3" width="100%">
				<blockquote>
				<form action="<? echo URLHelper::getLink('#anker') ?>" method="POST">
					<select name="open" style="vertical-align:middle">
						<? echo $select ?>
					</select>
					<input type="text" name="top_folder_name" size="50">
					<input type="image" name="anlegen" value="<?=_("Neuer Ordner")?>" <?=makeButton("neuerordner", "src")?> border="0">
				</form>
				</blockquote>
				<?
				}
			}
	} elseif($folder_system_data['mode']){
		echo "\n" . '<td class="blank" align="center" colspan="3" width="100%" >';
		echo "\n" . '<span style="margin:25px;font-weight:bold;">';
		echo "\n" . ($folder_system_data["mode"] == 'move' ? _("Verschiebemodus") : _("Kopiermodus")) . "</span>";
		echo "\n" . '<a href="'.URLHelper::getLink('?cmd=tree').'">'. makeButton("abbrechen", "img",_("Verschieben / Kopieren abbrechen")) . '</a>';
		echo "\n" . '</td></tr>';
	}

	//when changing, uploading or show all (for download selector), create a form
	if ((($change) || ($folder_system_data["cmd"]=="all")) && (!$folder_system_data["upload"])) {
		echo "<form method=\"post\" action=\"".URLHelper::getLink('')."\">";
		}

	print "<tr><td class=\"blank\" colspan=\"3\" width=\"100%\">";


	if ($folder_system_data["cmd"]=="all") {
		print "<blockquote><font size='-1'>";
		printf (_("Hier sehen Sie alle Dateien, die zu dieser %s eingestellt wurden. Wenn Sie eine neue Datei einstellen m&ouml;chten, w&auml;hlen Sie bitte die Ordneransicht und &ouml;ffnen den Ordner, in den Sie die Datei einstellen wollen."), $SessSemName["art_generic"]); 
		print "</font></blockquote>";
	}
	
	$lastvisit = object_get_visit($SessSemName[1], "documents");
	$query = "SELECT * " .
			"FROM dokumente " .
			"WHERE seminar_id = '$range_id' " .
			"AND user_id != '".$user->id."' " .
			"AND ( chdate > '".(($lastvisit) ? $lastvisit : time())."' " .
					"OR mkdate > '".(($lastvisit) ? $lastvisit : time())."')";
	$result = $db->query($query)->fetchAll();
	if (count($result)>0) {
		print "<blockquote><font size='-1'>";
		print _("Es gibt ");
		print "<b>".(count($result)>1 ? count($result) : _("eine"))."</b>";
		print _(" neue/ge�nderte Dateie(n). Jetzt ");
		print " <a href=\"".URLHelper::getLink("?zipnewest=".$lastvisit)."\">" . makeButton("herunterladen", "img") . "</a>";
		print "</font></blockquote>";
	}
	
	//Treeview in Ordnerstruktur
	if ($folder_system_data["cmd"]=="tree") {
		
		print "<style>
div.droppable {
	border: 1pt solid white;
	margin-top: 0;
	margin-bottom: 0;
}
div.droppable.hover {
	border: 1pt solid red;
	margin-top: 0;
	margin-bottom: 0;
}
</style>";
		
		print '<table border=0 cellpadding=0 cellspacing=0 width="100%"><tr>';
		print "<td class=\"blank\" valign=\"top\" heigth=21 nowrap width=1px>&nbsp;</td>";
		print "<td>";
		print "<div class=\"\" id=\"folder_subfolders_root\">"; //class = "folder_container" for sorting
		//Seminar...
		//Algemeiner Dateienordner
		$folders = $db->query("SELECT folder_id FROM folder WHERE range_id = '$range_id' ORDER BY name")->fetchAll();
		foreach($folders as $general_folder) {
			if ($folder_tree->isExecutable($general_folder["folder_id"], $user->id) || $rechte) {
				display_folder($general_folder["folder_id"], 
						$folder_system_data["open"], 
						$change, 
						$folder_system_data["move"], 
						$folder_system_data["upload"], 
						$folder_system_data["refresh"], 
						$folder_system_data["link"],
						$open_id,
						NULL,
						false);
			}
		}
		//display_folder_system($range_id, 0, $folder_system_data["open"], '', $change, $folder_system_data["move"], $folder_system_data["upload"], $folder_system_data["refresh"], $folder_system_data["link"]);
		
		
		//Weitere Ordner:
		$folders = $db->query("SELECT folder_id " .
				"FROM folder " .
				"WHERE range_id = '".md5($SessionSeminar . 'top_folder')."' " .
				"ORDER BY name")->fetchAll();
		foreach($folders as $general_folder) {
			if ($folder_tree->isExecutable($general_folder['folder_id'], $user->id) || $rechte) {
				display_folder($general_folder["folder_id"], 
						$folder_system_data["open"], 
						$change, 
						$folder_system_data["move"], 
						$folder_system_data["upload"], 
						$folder_system_data["refresh"], 
						$folder_system_data["link"],
						$open_id,
						NULL,
						false);
			}
		}
		
		// Themenordner zu Terminen:
		if($SessSemName['class'] == 'sem') {
			$query = "SELECT DISTINCT folder_id " .
			    "FROM themen as th " . 
			    "LEFT JOIN themen_termine as tt ON(th.issue_id = tt.issue_id) " .
			    "LEFT JOIN termine as t ON (t.termin_id = tt.termin_id) " .
			    "INNER JOIN folder ON (th.issue_id=folder.range_id) " .
			  "WHERE th.seminar_id='$range_id' " . 
			  "ORDER BY th.title, t.date, th.priority";
			$result = $db->query($query)->fetchAll();
			foreach ($result as $row) {
				display_folder($row['folder_id'], 
				    $folder_system_data["open"], 
				    $change, 
				    $folder_system_data["move"], 
				    $folder_system_data["upload"], 
				    $folder_system_data["refresh"], 
				    $folder_system_data["link"], 
				    $open_id, 
				    NULL, 
				    true);
			}
			
			//Gruppenordner:
			$query = "SELECT sg.statusgruppe_id FROM statusgruppen sg "
					. (!$rechte ? "INNER JOIN statusgruppe_user sgu ON sgu.statusgruppe_id=sg.statusgruppe_id AND sgu.user_id='$user->id'" : "")
					. " INNER JOIN folder ON sg.statusgruppe_id=folder.range_id WHERE sg.range_id='$range_id' ORDER BY sg.position";
			$result2 = $db->query($query)->fetchAll();
			foreach ($result2 as $row2) {
				$folders = $db->query("SELECT folder_id FROM folder WHERE range_id = '".$row2["statusgruppe_id"]."'")->fetchAll();
				foreach ($folders as $folder) {
					if ($folder_tree->isExecutable($folder["folder_id"], $user->id) || $rechte) {
						display_folder($folder["folder_id"], 
							$folder_system_data["open"], 
							$change, 
							$folder_system_data["move"], 
							$folder_system_data["upload"], 
							FALSE, 
							$folder_system_data["refresh"], 
							$folder_system_data["link"],
							$open_id,
							NULL,
							false);
					}
				}
			}
		  print "</div>";
		  print '</td><td width=1px>&nbsp;</td></tr></table>';
		}
	}	else {
		//Flatview ohne Ordnerstruktur
		print '<table border=0 cellpadding=0 cellspacing=0 width="100%">';
		print "<tr>" .
				"<td class=\"blank\"></td><td class=\"blank\"><div align=\"right\">" .
					"<a href=\"".URLHelper::getLink("?check_all=TRUE")."\">".makeButton("alleauswaehlen")."</a>" .
					"&nbsp;<input style=\"vertical-align: middle;\" type=\"IMAGE\" name=\"download_selected\" border=\"0\" ".makeButton("herunterladen", "src")." />&nbsp;</div>" .
				"</td><td class=\"blank\"></td></tr> <tr><td></td><td class=\"blank\">&nbsp;</td><td class=\"blank\"></td></tr>";
		$dreieck_runter = "dreieck_down.png";
		$dreieck_hoch = "dreieck_up.png";
		print "<tr><td></td><td><table border=0 cellpadding=0 cellspacing=0 width=\"100%\">" .
				"<tr>" .
				"<td class=\"steelgraudunkel\">&nbsp;&nbsp;&nbsp;";
				
		print "<a href=\"".URLHelper::getLink((($folder_system_data['orderby'] != "type") ? "?orderby=type" : "?orderby=type_rev"))."\">";
		print "<b>"._("Typ")."</b>".
			($folder_system_data['orderby'] == "type_rev" 
				? "<img style=\"vertical-align:middle\" border=0 src=\"".$GLOBALS['ASSETS_URL']."images/$dreieck_hoch\">" 
				: ($folder_system_data['orderby'] == "type" ? "<img style=\"vertical-align:middle\" border=0 src=\"".$GLOBALS['ASSETS_URL']."images/$dreieck_runter\">" : "")).
			"</a>&nbsp;&nbsp; ";
		
		
		print "<a href=\"".URLHelper::getLink((($folder_system_data['orderby'] != "filename") ? "?orderby=filename" : "?orderby=filename_rev"))."\">";
		print "<b>"._("Name")."</b>".
			($folder_system_data['orderby'] == "filename_rev" 
				? "<img style=\"vertical-align:middle\" border=0 src=\"".$GLOBALS['ASSETS_URL']."images/$dreieck_hoch\">" 
				: ($folder_system_data['orderby'] == "filename" ? "<img style=\"vertical-align:middle\" border=0 src=\"".$GLOBALS['ASSETS_URL']."images/$dreieck_runter\">" : "")).
			"</a>&nbsp;&nbsp; ";
		
		print "<a href=\"".URLHelper::getLink((($folder_system_data['orderby'] != "size_rev") ? "?orderby=size_rev" : "?orderby=size"))."\">";
		print "<b>"._("Gr��e")."</b>".
			($folder_system_data['orderby'] == "size" 
				? "<img style=\"vertical-align:middle\" border=0 src=\"".$GLOBALS['ASSETS_URL']."images/$dreieck_hoch\">" 
				: ($folder_system_data['orderby'] == "size_rev" ? "<img style=\"vertical-align:middle\" border=0 src=\"".$GLOBALS['ASSETS_URL']."images/$dreieck_runter\">" : "")).
			"</a>&nbsp;&nbsp; ";
		
		print "<a href=\"".URLHelper::getLink((($folder_system_data['orderby'] != "downloads") ? "?orderby=downloads" : "?orderby=downloads_rev"))."\">";
		print "<b>"._("Downloads")."</b>".
			($folder_system_data['orderby'] == "downloads_rev" 
				? "<img style=\"vertical-align:middle\" border=0 src=\"".$GLOBALS['ASSETS_URL']."images/$dreieck_hoch\">" 
				: ($folder_system_data['orderby'] == "downloads" ? "<img style=\"vertical-align:middle\" border=0 src=\"".$GLOBALS['ASSETS_URL']."images/$dreieck_runter\">" : "")).
			"</a>&nbsp;&nbsp; ";
		
		print "</td><td class=\"steelgraudunkel\" align=right>";
		
		print "<a href=\"".URLHelper::getLink((($folder_system_data['orderby'] != "autor") ? "?orderby=autor" : "?orderby=autor_rev"))."\">";
		print "<b>"._("Autor")."</b>".
			($folder_system_data['orderby'] == "autor_rev" 
				? "<img style=\"vertical-align:middle\" border=0 src=\"".$GLOBALS['ASSETS_URL']."images/$dreieck_hoch\">" 
				: ($folder_system_data['orderby'] == "autor" ? "<img style=\"vertical-align:middle\" border=0 src=\"".$GLOBALS['ASSETS_URL']."images/$dreieck_runter\">" : "")).
			"</a>&nbsp;&nbsp; ";
		
		print "<a href=\"".URLHelper::getLink((($folder_system_data['orderby'] != "date_rev") ? "?orderby=date_rev" : "?orderby=date"))."\">";
		print "<b>"._("Datum")."</b>".
			(($folder_system_data['orderby'] == "date"  || (!$folder_system_data['orderby']))
				? "<img style=\"vertical-align:middle\" border=0 src=\"".$GLOBALS['ASSETS_URL']."images/$dreieck_hoch\">" 
				: (($folder_system_data['orderby'] == "date_rev") ? "<img style=\"vertical-align:middle\" border=0 src=\"".$GLOBALS['ASSETS_URL']."images/$dreieck_runter\">" : "")).
			"</a>&nbsp;&nbsp; ";
		
		print "</td</tr></table></td><td>";
		print '<tr>';
		print "<td class=\"blank\" valign=\"top\" heigth=21 nowrap width=1px>&nbsp;</td>";
		print "<td id=\"folder_1\">";
		
		//Ordnen nach: Typ, Name, Gr��e, Downloads, Autor, Alter
		$query = "SELECT ". $_fullname_sql['full'] ." AS fullname, username, a.user_id, a.*, IF(IFNULL(a.name,'')='', a.filename,a.name) AS t_name, a.range_id FROM dokumente a LEFT JOIN auth_user_md5 USING (user_id) LEFT JOIN user_info USING (user_id) WHERE seminar_id = '$range_id'";
		if ($folder_system_data['orderby'] == "type")
			$query .= " ORDER BY SUBSTRING_INDEX(a.filename, '.', -1) ASC";
		if ($folder_system_data['orderby'] == "type_rev")
			$query .= " ORDER BY SUBSTRING_INDEX(a.filename, '.', -1) DESC";
		if ($folder_system_data['orderby'] == "filename")
			$query .= " ORDER BY t_name ASC, a.chdate DESC";
		if ($folder_system_data['orderby'] == "filename_rev")
			$query .= " ORDER BY t_name DESC, a.chdate ASC";
		if ($folder_system_data['orderby'] == "size") 
			$query .= " ORDER BY a.filesize DESC";
		if ($folder_system_data['orderby'] == "size_rev") 
			$query .= " ORDER BY a.filesize ASC";
		if ($folder_system_data['orderby'] == "downloads") 
			$query .= " ORDER BY a.downloads DESC, t_name ASC, a.chdate DESC";
		if ($folder_system_data['orderby'] == "downloads_rev") 
			$query .= " ORDER BY a.downloads ASC, t_name DESC, a.chdate ASC";
		if ($folder_system_data['orderby'] == "autor")
			$query .= " ORDER BY ". $_fullname_sql['no_title_rev'] ." ASC";
		if ($folder_system_data['orderby'] == "autor_rev")
			$query .= " ORDER BY ". $_fullname_sql['no_title_rev'] ." DESC";
		if (($folder_system_data['orderby'] == "date") || (!$folder_system_data['orderby'])) //default-wert
			$query .= " ORDER BY a.chdate DESC";
		if ($folder_system_data['orderby'] == "date_rev")
			$query .= " ORDER BY a.chdate ASC";
		$result2 = $db->query($query)->fetchAll();
		foreach ($result2 as $datei) {
			if ($folder_tree->isReadable($datei['range_id'], $user->id)) {
				display_file_line($datei, 
						$range_id, 
						$folder_system_data["open"], 
						$change, 
						$folder_system_data["move"], 
						$folder_system_data["upload"], 
						TRUE, 
						$folder_system_data["refresh"], 
						$folder_system_data["link"], 
						$open_id);
			}
		}
		
		//display_folder_system($range_id, 0,$folder_system_data["open"], '', $change, $folder_system_data["move"], $folder_system_data["upload"], TRUE, $folder_system_data["refresh"], $folder_system_data["link"]);
		
		print '</td><td width=1px class="blank">&nbsp;</td></tr>';
	}

	//und Form wieder schliessen
	if ($change)
		echo "\n</form>";

	$folder_system_data["linkerror"]="";

	if ($folder_system_data["cmd"]=="tree") {
?>
		<br>
		</td>
	</tr>
</table>
<script type="text/javascript">  
//Initialisierung der Ordner und Dateien und verschwinden lassen der gelben Pfeile durch Anfasser:
STUDIP.Filesystem.unsetarrows();
STUDIP.Filesystem.setdraggables();
STUDIP.Filesystem.setdroppables();
</script>

<?php
	} else { //if $all
		if (!$folder_system_data["upload"] && !$folder_system_data["link"])
			print "<tr><td class=\"blank\">&nbsp;</td><td>";
			print "	<table border=0 cellpadding=0 cellspacing=0 width=\"100%\">";
			print "	<tr><td class=\"blank\"></td><td class=\"blank\" style=\"font-size: 4px;\">&nbsp;</td><td class=\"blank\"></td></tr>"; 
			print "	<tr><td class=\"steelgraudunkel\">&nbsp;";
			print "	</td><td class=\"steelgraudunkel\" align=right>";
			print "	&nbsp;</td></tr></table>";
			print "</td><td class=\"blank\">&nbsp;</td></tr>";
			
			print "<tr><td class=\"blank\"></td><td class=\"blank\"><div align=\"right\"><br><a href=\"".URLHelper::getLink("?check_all=TRUE")."\">".makeButton("alleauswaehlen")."</a>&nbsp;<input style=\"vertical-align: middle;\" type=\"IMAGE\" name=\"download_selected\" border=\"0\" ".makeButton("herunterladen", "src")." />&nbsp;</div></td><td class=\"blank\"></td></tr> <tr><td></td><td class=\"blank\">&nbsp;</td><td class=\"blank\"></td></tr>";
	}
	print "</table></form>";
	
	print "		<br>
		</td>
	</tr>
</table>";

?>
<div id="fehler_seite"></div>
<br>
<br>
<br>
<br>
<?php

include ('lib/include/html_end.inc.php');
page_close();
?>
