<?php
/**
 Copyright (C) 2018-2020 KANOUN Salim
 This program is free software; you can redistribute it and/or modify
 it under the terms of the Affero GNU General Public v.3 License as published by
 the Free Software Foundation;
 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 Affero GNU General Public Public for more details.
 You should have received a copy of the Affero GNU General Public Public along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA
 */

/**
 * "Upload Manager" Interface, to check patient's status visit per visit
 */

Session::checkSession();
$linkpdo=Session::getLinkpdo();

$userObject=new User($_SESSION['username'], $linkpdo);
$accessCheck=$userObject->isRoleAllowed($_SESSION['study'], $_SESSION['role']);

if ($accessCheck && $_SESSION['role'] == User::SUPERVISOR) {
	$username=$_SESSION['username'];
	
	//Get All visit / patient status from the visit Manager
	$studyObject=new Study($_SESSION['study'], $linkpdo);
	$possibleGroups=$studyObject->getAllPossibleVisitGroups();
	$allVisits=[];
	foreach ($possibleGroups as $groupObject) {
		$visitTypes=$groupObject->getAllVisitTypesOfGroup();
		$allVisits[$groupObject->groupModality]=array_map(function($visitType) {return $visitType->name; },$visitTypes);
	}
	
	require 'views/supervisor/upload_manager_view.php';
    
}else {
	require 'includes/no_access.php';
}
