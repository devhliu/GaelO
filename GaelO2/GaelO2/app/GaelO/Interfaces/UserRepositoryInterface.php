<?php

namespace App\GaelO\Interfaces;

interface UserRepositoryInterface {


    public function createUser(String $username, String $lastName, String $firstName, String $status,
                                String $email, ?String $phone, bool $administrator, int $centerCode, String $job,
                                ?String $orthancAdress, ?String $orthancLogin, ?String $orthancPassword,
                                String $passwordTemporary, ?String $password, String $creationDate) :array ;

    public function updateUser(int $id, String $username, ?String $lastName, ?String $firstName, String $status,
                                String $email, ?String $phone, bool $administrator, int $centerCode, String $job,
                                ?String $orthancAdress, ?String $orthancLogin, ?String $orthancPassword,
                                ?String $passwordTemporary) : void ;

    public function getUserByUsername(String $username, bool $withTrashed = false) : array ;

    public function isExistingUsername(String $username) : bool ;

    public function isExistingEmail(String $email) : bool ;

    public function reactivateUser(int $id) : void;

    public function getAdministratorsEmails() : array;

    public function getInvestigatorsStudyFromCenterEmails(string $study, int $centerCode, ?string $job) : array;

    public function getUsersEmailsByRolesInStudy(string $study, string $role ) : array;

    public function getUsersAffiliatedToCenter(int $centerCode) : array;

    public function getAllStudiesWithRoleForUser(string $username) : array;

    public function getUsersRoles(int $userId) : array;

    public function getUsersRolesInStudy(int $userId, String $study) : array;

    public function addUserRoleInStudy(int $userId, String $study, string $role) : void;

    public function deleteRoleForUser(int $userId, String $study, String $role) : void;

    public function addAffiliatedCenter(int $userId, int $centerCode) : void;

    public function deleteAffiliatedCenter(int $userId, int $centerCode) : void;

    public function getAffiliatedCenter(int $userId) : array;

    public function getAllUsersCenters(int $userId) : array;

    public function getUsersFromStudy(string $studyName) : array;

}