<?php

/**
* On récupère les infos de qualification avec son Id
* @param $qualification_id
* @return int|mixed
*/
public function getQualificationById($qualification_id)
{
    try {
        $req = $this->conn->prepare("SELECT * FROM cfg_qualifications WHERE Id = :id");
        $req->bindParam(':id', $qualification_id);
        $req->execute();

        if ($req->rowCount() === 0) {
            return "";
        }
        $result = $req->fetch();
        $req = null;
        return $result;

    } catch (\Exception $e) {
        $this->logger->error($e->getMessage());
        return "";
    }
}

/**
* On récupère toutes les catégories
* @return int|mixed
*/
public function getQualifCategories() {
    try {
        $req = $this->conn->prepare("SELECT * FROM cfg_qualifications_categories");
        $req->execute();

        if ($req->rowCount() === 0) {
            return array();
        }
        $result = $req->fetchAll();
        return $result;

    } catch (\Exception $e) {
        $this->logger->error($e->getMessage());
        return array();
    }
}

/**
* On récupère les qualifs par rapport aux catégories
* @param $categoryId
* @return int|mixed
*/
public function getQualifsByCategory($categoryId) {
    try {
        $req = $this->conn->prepare("SELECT * FROM cfg_qualifications WHERE CategoryId = :category_id");
        $req->bindParam(':category_id', $categoryId);
        $req->execute();

        if ($req->rowCount() === 0) {
            return "";
        }
        $result = $req->fetchAll(\PDO::FETCH_ASSOC);
        $req = null;
        return $result;

    } catch (\Exception $e) {
        $this->logger->error($e->getMessage());
        return "";
    }
}

/**
* On récupère les infos d'une catégorie de qualification par rapport à son id
* @param $categoryId
* @return int|mixed
*/
public function getQualifCategoryById($categoryId)
{
    try {
        $req = $this->conn->prepare("SELECT * FROM cfg_qualifications_categories WHERE Id = :id");
        $req->bindParam(':id', $categoryId);
        $req->execute();

        if ($req->rowCount() === 0) {
            return "";
        }
        $result = $req->fetch();
        $req = null;
        return $result;

    } catch (\Exception $e) {
        $this->logger->error($e->getMessage());
        return "";
    }
}

/**
* Récupération de la liste des services en fonctions d'une liste de groupe
* @param type $groupList
* @param type $withRecordings
* @return array
*/
public function getServicesFromGroup($groupList, $withRecordings = true) {
    $groupListIn = "";
    if (!empty($groupList)) {
        $groupListIn = implode(",", $groupList);
    }
    try {
        if ($withRecordings) {
            $req = $this->conn->prepare("SELECT DISTINCT S.ServiceId, S.ServiceName, S.ServiceGroupId FROM cfg_service S RIGHT JOIN histo_recordcall RC USING(ServiceId) WHERE S.ServiceGroupId IN ({$groupListIn}) ORDER BY 2");
        } else {
            $req = $this->conn->prepare("SELECT ServiceId, ServiceName, ServiceGroupId FROM cfg_service S WHERE S.ServiceGroupId IN ({$groupListIn}) ORDER BY 2");
        }
        $req->execute();

        if ($req->rowCount() === 0) {
            return array();
        }
        $result = $req->fetchAll();
        return $result;
    } catch (\Exception $e) {
        $this->logger->error($e->getMessage());
        return array();
    }
}

