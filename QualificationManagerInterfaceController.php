<?php

namespace Taas\TaasInterface\Controller;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Taas\Dao\Exception\DaoCrudException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Taas\Dao\Model\QualificationsManager\Category;
use Taas\Dao\Model\QualificationsManager\MappingCategoryQualifs;
use Taas\Dao\Model\QualificationsManager\MappingCategoryService;
use Taas\TaasInterface\Entities\TaasInterfaceError;
use Taas\TaasInterface\Provider\AgentSessionProvider;
use Taas\TaasInterface\Provider\UserSessionProvider;


/**
 * Controller pour l'interface de paramétrage
 * du service QualificationsManager sur le dashboard
 * @package Taas\TaasInterface\Controller
 */

/**
 * TODO !!!!!!!!
 *  - Dans la modale de création c'est pareil avec les services. Il faut faire un event sur le onChange des 2 select => en fonction de la catégorie sélectionnée on va chercher les services adaptés
 *      (j'ai aussi créé la route avec la fonction permettant de récupérer les services en fonction de la catégorie => à finaliser
 *   + bien vérifier qu'un service n'est associé qu'à une seule catégorie (1 catégorie peut être associée à plusieurs services mais pas l'inverse) => contrôle côté back lors de la création du service
 *  - Penser à implémenter la dao des kiamo 65 et 75
 */

class QualificationsManagerInterfaceController {

    protected $container;
    private $log;

    const SERVICE_NAME = "QualificationsManager";

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->log = $this->container->get('logger');
    }

    /**
     * Affichage de la page d'accueil
     * @param Request $request
     * @param Response $response
     * @param $args
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function index(Request $request, Response $response, $args) {

        $this->container->get('tools')->index($response, self::SERVICE_NAME);
    }

    /**
     * Affichage de la liste des catégories
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return mixed
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function displayAdminTableCategories(Request $request, Response $response, $args) {

        // Récupération de l'utilisateur
        $user = UserSessionProvider::getAuth();

        // Récupération du client en fonction de l'utilisateur
        $clientRepo = $this->container->get('FactoryRepository')->getRepository('Taas\\Dao\\Repository\\Account\\RepositoryClient');
        $client = $clientRepo->findByCache($user->getCurrentClientId());

        // Vérification si le client possède le service "qualification manager"
        $qualificationsManagerService = $client->getService(self::SERVICE_NAME);
        if ($qualificationsManagerService == null) {
            throw new TaasInterfaceError("Le client {$user->getCurrentClientId()} ne possède pas le service" . self::SERVICE_NAME, 404);
        }
        $userAllowed = $qualificationsManagerService->checkIfUserAllowed($user);
        if(!$userAllowed){
            throw new TaasInterfaceError("Accès interdit pour ".$user->getFirstName(). " ".$user->getName(), 403);
        }
        // Récupération des catégories déjà paramétrées par le client
        $categoriesRepo = $this->container->get('FactoryRepository')->getRepository('Taas\\Dao\\Repository\\QualificationsManager\\RepositoryCategory');
        $clientCategories = $categoriesRepo->findByFilter([['columnname' => 'client_id', 'operator' => '=', 'value' => $client->id ]]);

        $categories = [];
        $subCategories = [];
        $taasCategories = [];

        // Parcours des catégories
        foreach ($clientCategories as $category) {
            //Récupération de la catégorie parente
            $parentName = '';
            if (!empty($category->parent_id)) {
                $parentCategory = $categoriesRepo->find($category->parent_id);
                $parentName = $parentCategory->name;
            }

            $categorie = [
                'id' => $category->id,
                'name' => $category->name,
                'parent_name' => $parentName,
                'sub_categories' => [],
                'crc_category_id' => $category->crc_category_id,
            ];

            $taasCategories[] = [
                'id' => $category->id,
                'name' => $category->name,
                'crc_category_id' => $category->crc_category_id,
            ];

            // Si la catégorie n'a pas de catégorie parente, elle est ajoutée à la liste des catégories principales
            if (empty($category->parent_id)) {
                $categories[] = $categorie;
            } else {
                // Sinon, elle est ajoutée à la liste des sous-catégories correspondant à sa catégorie parente
                $subCategories[$category->parent_id][] = $categorie;
            }
        }

        // Organisation récursive des sous-catégories
        foreach ($categories as &$categorie) {
            if (isset($subCategories[$categorie['id']])) {
                $categorie['sub_categories'] = $this->organiseSubCategories($subCategories[$categorie['id']], $subCategories);
            }
        }

        $kiamoDb = $this->container->get('tools')->getKiamoDbConfIfAccess($client);
        $kiamoCategories =  $kiamoDb->getQualifCategories();

        // Rendu de la vue avec les données à afficher
        return $this->container->get('view')->render($response, 'QualificationsManager\telsi\myservice\category_admin.html.twig', [
            'shortLang' => UserSessionProvider::getShortLocalLanguage(),
            'user' => $user,
            'currentClientName' => $client->name ?? "", 
            'currentClientLogo' => $client->picture ?? "",
            'clientService' => $qualificationsManagerService,
            "title" => "Gestion des catégories",
            "categories" => json_encode($categories),
            "taasCategories" => $taasCategories,
            "kiamoCategories" => $kiamoCategories,
            "current_sub_menu" => 1
        ]);
    }

    public function getCategoryCrcServices(Request $request, Response $response, $args)
    {

        // Récupération de l'utilisateur
        $user = UserSessionProvider::getAuth();

        //Vérif possession du service et autorisations
        $clientRepo = $this->container->get('FactoryRepository')->getRepository('Taas\\Dao\\Repository\\Account\\RepositoryClient');
        $client = $clientRepo->findByCache($user->getCurrentClientId());
        $qualificationsManagerService = $client->getService(self::SERVICE_NAME);
        if ($qualificationsManagerService == null) {
            throw new TaasInterfaceError("Le client {$user->getCurrentClientId()} ne possède pas le service" . self::SERVICE_NAME, 404);
        }
        $userAllowed = $qualificationsManagerService->checkIfUserAllowed($user);
        if (!$userAllowed) {
            throw new TaasInterfaceError("Accès interdit pour " . $user->getFirstName() . " " . $user->getName(), 403);
        }

        //Récupération de la catégorie Kiamo
        $categoryId = $args['categoryId'] ?? '';

        // Récupération du groupe associé à la catégorie Kiamo pour ensuite ne récupérer que les services appartenant à ce groupe
        $kiamoDb = $this->container->get('tools')->getKiamoDbConfIfAccess($client);
        $kiamoCategory =  $kiamoDb->getQualifCategoryById($categoryId);
        $groupId = ($kiamoCategory != null) ? $kiamoCategory['GroupId'] : '';
        $services =  $kiamoDb->getServicesByGroup($groupId);

        return $response->withJson($services)->withStatus(200);
    }

    /**
     * Récupération des qualifications Kiamo associées à une catégorie
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return mixed
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function getCategoryCrcQualifications(Request $request, Response $response, $args)
    {
        // Récupération de l'utilisateur
        $user = UserSessionProvider::getAuth();

        //Vérif possession du service et autorisations
        $clientRepo = $this->container->get('FactoryRepository')->getRepository('Taas\\Dao\\Repository\\Account\\RepositoryClient');
        $client = $clientRepo->findByCache($user->getCurrentClientId());
        $qualificationsManagerService = $client->getService(self::SERVICE_NAME);
        if ($qualificationsManagerService == null) {
            throw new TaasInterfaceError("Le client {$user->getCurrentClientId()} ne possède pas le service" . self::SERVICE_NAME, 404);
        }
        $userAllowed = $qualificationsManagerService->checkIfUserAllowed($user);
        if (!$userAllowed) {
            throw new TaasInterfaceError("Accès interdit pour " . $user->getFirstName() . " " . $user->getName(), 403);
        }

        //Récupération de la catégorie Kiamo
        $kiamoCategoryId = $args['kiamoCategoryId'] ?? '';
        $taasCategoryId = $args['taasCategoryId'] ?? '';

        // Récupération des qualifications Kiamo qui font partie de cette catégorie
        $kiamoDb = $this->container->get('tools')->getKiamoDbConfIfAccess($client);
        $kiamoQualifications =  $kiamoDb->getQualifsByCategory($kiamoCategoryId);

        $mappingCategoryQualifs = $this->container->get('FactoryRepository')->getRepository('Taas\\Dao\\Repository\\QualificationsManager\\RepositoryMappingCategoryQualifs');
        $mappings = $mappingCategoryQualifs->findByFilter([['columnname' => 'category_id', 'operator' => '=', 'value' => $taasCategoryId]]);

        $qualifications = array();
        if(!empty($kiamoQualifications)){
            foreach($kiamoQualifications as $qualif){
                $qualif['associated'] = false;
                foreach($mappings as $mapping){
                    if($mapping->crc_qualif_id == $qualif['Id']){
                        $qualif['associated'] = true;
                    }
                }
                $qualifications[] = $qualif;
            }
        }
        return $response->withJson($qualifications)->withStatus(200);
    }

    /**
     * Organiser les sous-catégories de manière récursive
     * @param array $subCategories
     * @param array $subCategoriesList
     * @return array
     */
    private function organiseSubCategories($subCategories, $subCategoriesList) {
        $organisedSubCategories = [];

        // Parcours des sous-catégories
        foreach ($subCategories as $subCategory) {
            // Organisation récursive des sous-catégories
            $subCategory['sub_categories'] = $this->organiseSubCategories($subCategoriesList[$subCategory['id']] ?? [], $subCategoriesList);
            $organisedSubCategories[] = $subCategory;
        }

        return $organisedSubCategories;
    }

    /**
     * Création d'une nouvelle catégorie
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return mixed
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function createCategory(Request $request, Response $response, $args)
    {
        $user = UserSessionProvider::getAuth();  //infos client
        $clientRepo = $this->container->get('FactoryRepository')->getRepository('Taas\\Dao\\Repository\\Account\\RepositoryClient');
        $client = $clientRepo->findByCache($user->getCurrentClientId());
        $service = $client->getService(self::SERVICE_NAME);
        if ($service == null) {
            throw new TaasInterfaceError("Le client {$user->getCurrentClientId()} ne possède pas le service " . self::SERVICE_NAME, 404);
        }
        $userAllowed = $service->checkIfUserAllowed($user);
        if(!$userAllowed){
            throw new TaasInterfaceError("Accès interdit pour ".$user->getFirstName(). " ".$user->getName(), 403);
        }

        //Récupération des données du formulaire
        $postData = $request->getParsedBody();
        $categoryName = (isset($postData["textCategory"])) ? filter_var(trim($postData["textCategory"]), FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES) : "";
        $typeCategory = (isset($postData["categoryTypeSelector"])) ? filter_var(trim($postData["categoryTypeSelector"]), FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES) : "";
        $taasParentCategoryId = (isset ($postData["selectedCategoryTaas"])) ? filter_var($postData["selectedCategoryTaas"], FILTER_VALIDATE_INT) : '';
        $kiamoParentCategoryId = (isset ($postData["selectedCategoryKiamo"])) ? filter_var($postData["selectedCategoryKiamo"], FILTER_VALIDATE_INT) : '';
        $selectedServiceId = ($postData["selectedService"] != "") ? filter_var($postData["selectedService"], FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '#^(\d+,?)*\d$#'))) : -1;

        //Contrôle des champs obligatoires (en plus de la vérification côté client)
        if ($categoryName == '') {
            $this->container->get('flash')->addMessage("error", "Veuillez renseigner le nom d'une catégorie");
            return $response->withRedirect($this->container->get('router')->pathFor('interface_qualificationsmanager_admin'));
        }
        if ($typeCategory == '') {
            $this->container->get('flash')->addMessage("error", "Veuillez renseigner une catégorie");
            return $response->withRedirect($this->container->get('router')->pathFor('interface_qualificationsmanager_admin'));
        }

        //Si des services sont associés on transforme la suite de string en tableau pour les parcourir ultérieurement
        $services = ($selectedServiceId != -1) ? explode(",", $selectedServiceId) : array();

        //Vérification de l'unicité de la catégorie
        $categoriesManagerRepo = $this->container->get('FactoryRepository')->getRepository('Taas\\Dao\\Repository\\QualificationsManager\\RepositoryCategory');
        $categoriesManager = $categoriesManagerRepo->findByFilter([['columnname' => 'name', 'operator' => '=', 'value' => $categoryName]]);

        if (count($categoriesManager) > 0) { 
            $this->container->get('flash')->addMessage("error", "Erreur lors de l'enregistrement. Une catégorie avec le même nom existe déjà");
            return $response->withRedirect($this->container->get('router')->pathFor('interface_qualificationsmanager_admin'));
        }

        $categoryTypeSelector = $postData["categoryTypeSelector"];
        $categoryCRC = $categoriesManagerRepo->findByFilter([['columnname' => 'id', 'operator' => '=', 'value' => $taasParentCategoryId]]);

        //On construit notre objet QualificationManager avec les données reçus
        $categoriesManager = new Category();
        $categoriesManager->name = $categoryName;
        if ($categoryTypeSelector == 'kiamo') {
            $categoriesManager->parent_id = null;
            $categoriesManager->crc_category_id = $kiamoParentCategoryId;
        } else if ($categoryTypeSelector == 'taas') {
            $categoriesManager->parent_id = $taasParentCategoryId;
            $categoriesManager->crc_category_id = $categoryCRC[0]->crc_category_id;
        }
        $categoriesManager->client_id = $user->getCurrentClientId();
        
        $newCategoryId = $categoriesManagerRepo->insert($categoriesManager);

        $qualificationManagerMappingRepo = $this->container->get('FactoryRepository')->getRepository('Taas\\Dao\\Repository\\QualificationsManager\\RepositoryMappingCategoryService');
        $selectedService = $postData["selectedService"];

        $mapping = new MappingCategoryService();
        $mapping->category_id = $newCategoryId;
        if ($selectedService == '-1') {
            $mapping->crc_service_id = null;
        } else {
            $mapping->crc_service_id = $selectedServiceId;
        }

        try {
            $qualificationManagerMappingRepo->insert($mapping);
            $this->container->get('flash')->addMessage("success", "La catégorie a bien été créée");

        } catch (DaoCrudException $e) {
            $this->container->get('flash')->addMessage('error', "Erreur lors de la création. Veuillez contacter l'administrateur");
            $this->log->error("Erreur lors de l'insertion dans la table category ou mapping :" . $e->getMessage());
        }

        return $response->withRedirect($this->container->get('router')->pathFor('interface_qualificationsmanager_admin'));
    }

    /**
     * Suppression des catégories
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return mixed
     */
    public function deleteCategory(Request $request, Response $response, $args)
    {
        $user = UserSessionProvider::getAuth();  //infos client
        $clientRepo = $this->container->get('FactoryRepository')->getRepository('Taas\\Dao\\Repository\\Account\\RepositoryClient');
        $client = $clientRepo->findByCache($user->getCurrentClientId());
        $service = $client->getService(self::SERVICE_NAME);
        if ($service == null) {
            throw new TaasInterfaceError("Le client {$user->getCurrentClientId()} ne possède pas le service " . self::SERVICE_NAME, 404);
        }
        $userAllowed = $service->checkIfUserAllowed($user);
        if(!$userAllowed){
            throw new TaasInterfaceError("Accès interdit pour ".$user->getFirstName(). " ".$user->getName(), 403);
        }

        $postData = $request->getParsedBody();

        $categoryId = isset($postData['deleteCategoryId']) ? $postData['deleteCategoryId'] : [];

        //On récupère la catégorie à supprimer à partir de son id
        $categoryRepo = $this->container->get('FactoryRepository')->getRepository('Taas\\Dao\\Repository\\QualificationsManager\\RepositoryCategory');
        $deletedCategory = $categoryRepo->find($categoryId);
        if ($deletedCategory == null) {
            throw new TaasInterfaceError("La catégorie à supprimer n'a pas été trouvée en base", 404);
        }

        try {
            //1- Suppression du mapping
            $qualificationManagerMappingRepo = $this->container->get('FactoryRepository')->getRepository('Taas\\Dao\\Repository\\QualificationsManager\\RepositoryMappingCategoryService');
            $qualificationManagerMappingRepo->deleteByFilter([['columnname' => 'category_id', 'operator' => '=', 'value' => $categoryId]]);

            //2 - Suppression ensuite de la catégorie
            $categoryRepo->delete($categoryId);
            $this->container->get('flash')->addMessage('success', 'La catégorie a bien été supprimée');

        } catch (DaoCrudException $e) {
            $this->container->get('flash')->addMessage('error', "Erreur lors de la suppression. Veuillez choisir une catégorie fille");
            $this->log->error("Erreur lors d'un delete dans la table category ou mapping :" . $e->getMessage());
        }

        return $response->withRedirect($this->container->get('router')->pathFor('interface_qualificationsmanager_admin'));
    }

    /**
     * Modification des catégories
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return mixed
     */
    public function updateCategory(Request $request, Response $response, $args)
    {
        $user = UserSessionProvider::getAuth();  //infos client
        $clientRepo = $this->container->get('FactoryRepository')->getRepository('Taas\\Dao\\Repository\\Account\\RepositoryClient');
        $client = $clientRepo->findByCache($user->getCurrentClientId());
        $service = $client->getService(self::SERVICE_NAME);
        if ($service == null) {
            throw new TaasInterfaceError("Le client {$user->getCurrentClientId()} ne possède pas le service " . self::SERVICE_NAME, 404);
        }
        $userAllowed = $service->checkIfUserAllowed($user);
        if(!$userAllowed){
            throw new TaasInterfaceError("Accès interdit pour ".$user->getFirstName(). " ".$user->getName(), 403);
        }

        $postData = $request->getParsedBody();

        $categoryId = (isset($postData["editCategoryId"])) ? filter_var($postData["editCategoryId"], FILTER_VALIDATE_INT) : null;
        $name = (isset($postData["editCategoryName"])) ? filter_var(trim($postData["editCategoryName"]), FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES) : '';
        // $parentId = (isset ($postData["editCategoryParentId"])) ? filter_var($postData["editCategoryParentId"], FILTER_VALIDATE_INT) : '';
        $typeCategory = (isset($postData["categoryTypeSelectorUpdate"])) ? filter_var(trim($postData["categoryTypeSelectorUpdate"]), FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES) : "";
        $taasParentCategoryId = (isset ($postData["selectedCategoryTaas"])) ? filter_var($postData["selectedCategoryTaas"], FILTER_VALIDATE_INT) : '';
        $kiamoParentCategoryId = (isset ($postData["selectedCategoryKiamo"])) ? filter_var($postData["selectedCategoryKiamo"], FILTER_VALIDATE_INT) : '';

        // var_dump($taasParentCategoryId);
        // die;

        //On récupère la catégorie à modifier à partir de son id
        $categoryRepo = $this->container->get('FactoryRepository')->getRepository('Taas\\Dao\\Repository\\QualificationsManager\\RepositoryCategory');
        $updateCategory = $categoryRepo->find($categoryId);
        if ($updateCategory == null) {
            throw new TaasInterfaceError("La catégorie à modifier n'a pas été trouvée en base", 404);
        }

        if ($name == '') {
            $this->container->get('flash')->addMessage("error", "Veuillez renseigner le nom d'une catégorie");
            return $response->withRedirect($this->container->get('router')->pathFor('interface_qualificationsmanager_admin'));
        }
        if ($typeCategory == '') {
            $this->container->get('flash')->addMessage("error", "Veuillez renseigner une catégorie");
            return $response->withRedirect($this->container->get('router')->pathFor('interface_qualificationsmanager_admin'));
        }

        //On récupère la category à modifier à partir de son id
        $categoryToUpdate = $categoryRepo->find($categoryId);

        if ($categoryToUpdate == null) {
            throw new TaasInterfaceError("La catégorie modifiée n'a pas été trouvé en base", 404);
        }

        //Vérif qu'une autre catégorie avec le même nom n'existe pas
        $categoriesManagerRepo = $this->container->get('FactoryRepository')->getRepository('Taas\\Dao\\Repository\\QualificationsManager\\RepositoryCategory');
        $updateCategories = $categoriesManagerRepo->findByFilter([['columnname' => 'name', 'operator' => '=', 'value' => $categoryId]]);
        if (count($updateCategories) > 0) { 
            $this->container->get('flash')->addMessage("error", "Erreur lors de l'enregistrement. Une catégorie avec le même nom existe déjà");
            return $response->withRedirect($this->container->get('router')->pathFor('interface_qualificationsmanager_admin'));
        }

        $categoryCRC = $categoriesManagerRepo->findByFilter([['columnname' => 'id', 'operator' => '=', 'value' => $taasParentCategoryId]]);

        if ($categoryId == $taasParentCategoryId) { 
            $this->container->get('flash')->addMessage("error", "Erreur lors de l'enregistrement. Veuillez renseigner une catégorie parente pas identique à la catégorie");

            return $response->withRedirect($this->container->get('router')->pathFor('interface_qualificationsmanager_admin'));
        } else {
            if($typeCategory === "taasUpdate") {
                //Modification de la catégorie
                $updateCategory->name = $name;
                $updateCategory->parent_id = $taasParentCategoryId;
                $updateCategory->crc_category_id = $categoryCRC[0]->crc_category_id;
            } elseif($typeCategory === "kiamoUpdate") {
                $updateCategory->name = $name;
                $updateCategory->parent_id = null;
                $updateCategory->crc_category_id = $kiamoParentCategoryId;
            }

            try {
                $categoriesManagerRepo->update($updateCategory);
                $this->container->get('flash')->addMessage('success', 'La catégorie a bien été modifiée');
    
            } catch (DaoCrudException $e) {
                $this->container->get('flash')->addMessage('error', "Erreur lors de la modification. Veuillez contacter l'administrateur");
                $this->log->error("Erreur lors de l'update dans la table category ou mapping :" . $e->getMessage());
            }
        }

        return $response->withRedirect($this->container->get('router')->pathFor('interface_qualificationsmanager_admin'));
    }


    /**
     * Association de qualifications à une catégorie du TaaS
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return mixed
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function addQualifsToCategory(Request $request, Response $response, $args) {

        $user = UserSessionProvider::getAuth();  //infos client
        $clientRepo = $this->container->get('FactoryRepository')->getRepository('Taas\\Dao\\Repository\\Account\\RepositoryClient');
        $client = $clientRepo->findByCache($user->getCurrentClientId());
        $service = $client->getService(self::SERVICE_NAME);
        if ($service == null) {
            throw new TaasInterfaceError("Le client {$user->getCurrentClientId()} ne possède pas le service " . self::SERVICE_NAME, 404);
        }
        $userAllowed = $service->checkIfUserAllowed($user);
        if(!$userAllowed){
            throw new TaasInterfaceError("Accès interdit pour ".$user->getFirstName(). " ".$user->getName(), 403);
        }
        //Récupération des données postées
        $postData = $request->getParsedBody();
        $categoryId = (isset($postData["categoryId"])) ? filter_var($postData["categoryId"], FILTER_VALIDATE_INT) : '';
        $newQualifications = $postData["qualifications"] ?? array();

        //On récupère le paramétrage existant pour comparer et ajouter/supprimer en conséquence
        $mappingRepo = $this->container->get('FactoryRepository')->getRepository('Taas\\Dao\\Repository\\QualificationsManager\\RepositoryMappingCategoryQualifs');
        $mappings = $mappingRepo->findByFilter([['columnname' => 'category_id', 'operator' => '=', 'value' => $categoryId]]);

        $oldQualifications = array();
        if (count($mappings) > 0) {
            foreach ($mappings as $mapping) {
                $oldQualifications[] = $mapping->crc_qualif_id;
            }
        }
        $itemsToDelete = array_diff($oldQualifications, $newQualifications);
        if (count($itemsToDelete) > 0) {
            foreach ($itemsToDelete as $itemId) {
                $mappingRepo->deleteByFilter([['columnname' => 'category_id', 'operator' => '=', 'value' => $categoryId],['columnname' => 'crc_qualif_id', 'operator' => '=', 'value' => $itemId]]);
            }
        }
        $itemsToAdd = array_diff($newQualifications, $oldQualifications);
        if (count($itemsToAdd) > 0) {
            foreach ($itemsToAdd as $itemId) {
                //On vérifie que la qualification n'appartient pas à une autre catégorie du client (le cas échéant on ne l'ajoute pas et on le signale)
                $existingMapping =  $mappingRepo->findByFilter([['columnname' => 'category_id', 'operator' => 'IN', 'value' => $this->getClientCategoriesIds($client->id)],
                    ['columnname' => 'crc_qualif_id', 'operator' => '=', 'value' => $itemId]]);
                if(!empty($existingMapping)){
                    $this->container->get('flash')->addMessage('info', "Une ou plusieurs des qualifications cochées étant déjà associées à d'autres de vos catégories, elles n'ont pas été prises en compte");
                }else{
                    $newMapping = new MappingCategoryQualifs();
                    $newMapping->category_id = $categoryId;
                    $newMapping->crc_qualif_id = $itemId;
                    $mappingRepo->insert($newMapping);
                }
            }
        }
        $this->container->get('flash')->addMessage('success', "Les qualifications ont correctement été associées à la catégorie sélectionnée");
        return $response->withRedirect($this->container->get('router')->pathFor('interface_qualificationsmanager_admin'));
    }


    /**
     * Page de preview de la vue agent au sein de l'admin (permet de voir directement le rendu comme ça)
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function previewAgentView(Request $request, Response $response, $args) {

        // Récupération de l'utilisateur auth
        $user = UserSessionProvider::getAuth();

        // Récupération du client en fonction de l'utilisateur
        $clientRepo = $this->container->get('FactoryRepository')->getRepository('Taas\\Dao\\Repository\\Account\\RepositoryClient');
        $client = $clientRepo->findByCache($user->getCurrentClientId());

        // Vérification si le client possède le service "qualification manager"
        $qualificationsManagerService = $client->getService(self::SERVICE_NAME);
        if ($qualificationsManagerService == null) {
            throw new TaasInterfaceError("Le client {$user->getCurrentClientId()} ne possède pas le service" . self::SERVICE_NAME, 404);
        }
        $categories = $this->buildCategoriesArray($client);

        return $this->container->get('view')->render($response, 'QualificationsManager\telsi\agent\qualifications.html.twig', [
            "categories" => $categories,
            "mode" => "preview",
            "serviceCategory" => -1,
        ]);
    }

    /**
     * Affichage de la vue Agent
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function displayAgentInterface(Request $request, Response $response, $args) {

        // Récupération de l'utilisateur (côté agent)
        $agent = AgentSessionProvider::getAuth();
        // Récupération du client + vérifs possession service
        $clientRepo = $this->container->get('FactoryRepository')->getRepository('Taas\\Dao\\Repository\\Account\\RepositoryClient');
        $client = $clientRepo->findByCache($agent->getCurrentClientId());
        $service = $client->getService(self::SERVICE_NAME);
        if($service == null){
            throw new TaasInterfaceError("Le client {$agent->getCurrentClientId()} ne possède pas le service concerné", 404);
        }

        //Récupération des infos passées dans l'url
        $params = $request->getQueryParams();
        $agentId = (isset ($params['agentId'])) ? filter_var($params['agentId'], FILTER_VALIDATE_INT) : '';
        $serviceId = (isset ($params['serviceId'])) ? filter_var($params['serviceId'], FILTER_VALIDATE_INT) : '';
        $callRef =  (isset ($params['callRef'])) ? trim(htmlspecialchars($params['callRef'])) : '';

        if ($agentId == "") {
            throw new TaasInterfaceError("Identifiant agent obligatoire", 400);
        }
        if ($callRef == "") {
            throw new TaasInterfaceError("Identifiant appel obligatoire", 400);
        }
        if ($serviceId == "") {
            throw new TaasInterfaceError("Identifiant service obligatoire", 400);
        }
        $categories = $this->buildCategoriesArray($client);

        //Création de l'url d'authentification
        $authenticateUrl =  $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . TAAS_API_URLS['api_service_authenticate'];
        $authenticateUrl = str_replace("{{clientid}}", $agent->getCurrentClientId(), $authenticateUrl);

        //Création de l'url d'appel à notre service KiamoAPI => qui appellera l'api kiamo permettant de qualifier l'interaction
        $kiamoApiUrl =  $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . TAAS_API_URLS['api_service_kiamo_api_qualifInteraction'];
        $kiamoApiUrl = str_replace("{{clientid}}", $agent->getCurrentClientId(), $kiamoApiUrl);

        //Récupération de la catégorie associée au service concerné (pour faire arriver directement sur cette catégorie), s'il y en a une
        $serviceCategory = $this->getServiceCategory($serviceId);

        //Construction d'un tableau pour gérer le  breadcrumb (uniquement dans le cas où raccourci paramétré pour le service
        if($serviceCategory != -1){
            $shortcutBreadcrumb = $this->buildShortcutBreadcrumb($client, $serviceCategory);
            $breadcrumb = (!empty($shortcutBreadcrumb)) ? array_reverse($shortcutBreadcrumb) : array();
        }
        return $this->container->get('view')->render($response, 'QualificationsManager\telsi\agent\qualifications.html.twig', [
            "categories" => $categories,
            "mode" => "prod",
            "agentId" => $agentId,
            "serviceId" => $serviceId,
            "callRef" => $callRef,
            'authenticateUrl' => $authenticateUrl,
            'kiamoApiUrl' => $kiamoApiUrl,
            'token' =>  $client->api_token,
            'serviceCategory' => $serviceCategory,
            'breadcrumb' => $breadcrumb ?? array()
        ]);
    }

    /**
     * Récupération des catégories et qualifications paramétrées par le client
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    private function buildCategoriesArray($client): array
    {
        // Récupération des catégories paramétrées par le client
        $categoriesManagerRepo = $this->container->get('FactoryRepository')->getRepository('Taas\\Dao\\Repository\\QualificationsManager\\RepositoryCategory');
        $clientCategories = $categoriesManagerRepo->findByFilter([['columnname' => 'client_id', 'operator' => '=', 'value' => $client->id ]]);

        // Initialisation des tableaux pour les catégories et les sous-catégories
        $categories = [];
        $subCategories = [];

        // Parcours des catégories de façon récursive pour construire le tableau passé à la vue
        foreach ($clientCategories as $category) {
            $categorie = [
                'id'=> $category->id,
                'name' => $category->name,
                'sub_categories' => [],
                'qualifications' => $this->getCategoryQualifications($client, $category->id)
            ];

            // Si la catégorie n'a pas de catégorie parente, elle est ajoutée à la liste des catégories principales
            if (empty($category->parent_id)) {
                $categories[] = $categorie;
            } else {
                // Sinon, elle est ajoutée à la liste des sous-catégories correspondant à sa catégorie parente
                $subCategories[$category->parent_id][] = $categorie;
            }
        }
        // Organisation récursive des sous-catégories
        foreach ($categories as &$categorie) {
            if (isset($subCategories[$categorie['id']])) {
                $categorie['sub_categories'] = $this->organiseSubCategories($subCategories[$categorie['id']], $subCategories);
            }
        }
        return $categories;
    }


    /**
     * Réxupération de l'ensemble des qualifications associées à une catégorie
     * @param $client
     * @param $categoryId
     * @return array
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function getCategoryQualifications($client, $categoryId): array
    {

        // Récupération des qualifications paramétrées pour une catégorie
        $mappingRepo = $this->container->get('FactoryRepository')->getRepository('Taas\\Dao\\Repository\\QualificationsManager\\RepositoryMappingCategoryQualifs');
        $mappings = $mappingRepo->findByFilter([['columnname' => 'category_id', 'operator' => '=', 'value' => $categoryId]]);
        if(!empty($mappings)){
            $kiamoDb = $this->container->get('tools')->getKiamoDbConfIfAccess($client);

            foreach($mappings as $mapping){
                $qualification = $kiamoDb->getQualificationById($mapping->crc_qualif_id);
                $qualifs[$mapping->crc_qualif_id] = ($qualification != null) ? $qualification['Label'] : $mapping->crc_qualif_id;
            }
            return $qualifs ?? array();
        }else{
            return array();
        }
    }

    /**
     * Récupération des ids des catégories paramétrées par le client (soit tableua, soit liste)
     * @param $clientId
     * @param $inArray
     * @return array|string
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function getClientCategoriesIds($clientId, $inArray = false)
    {
        //On va chercher les groupes associés dans la table de mapping pour les afficher
        $categoryRepo = $this->container->get('FactoryRepository')->getRepository('Taas\\Dao\\Repository\\QualificationsManager\\RepositoryCategory');
        $categories = $categoryRepo->findByFilter([['columnname' => 'client_id', 'operator' => '=', 'value' => $clientId]]);
        $ids = array();
        foreach ($categories as $category) {
            $ids[] = $category->id;
        }
        if ($inArray) {
            return $ids;
        }
        return implode(',', $ids);
    }


    /**
     * Réxupération de la catégorie associée au service
     * @param $serviceId
     * @return null
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function getServiceCategory($serviceId): ?int
    {
        // Récupération des qualifications paramétrées pour une catégorie
        $mappingServiceRepo = $this->container->get('FactoryRepository')->getRepository('Taas\\Dao\\Repository\\QualificationsManager\\RepositoryMappingCategoryService');
        $mappings = $mappingServiceRepo->findByFilter([['columnname' => 'crc_service_id', 'operator' => '=', 'value' => $serviceId]]);

        if(!empty($mappings)){
            return $mappings[0]->category->id;
        }
        return -1;
    }

    /**
     * Construction du breadcrumb dans le cas où on a un service qui a été paramétré sur une catégorie spécifique
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function buildShortcutBreadcrumb($client, $serviceCategory, $breadcrumb = array())
    {
        $categoriesManagerRepo = $this->container->get('FactoryRepository')->getRepository('Taas\\Dao\\Repository\\QualificationsManager\\RepositoryCategory');
        $currentCategory = $categoriesManagerRepo->findByFilter([['columnname' => 'id', 'operator' => '=', 'value' => $serviceCategory ],['columnname' => 'client_id', 'operator' => '=', 'value' => $client->id]]);
        if(!empty($currentCategory)){
            $breadcrumb[] = array('id' => $currentCategory[0]->id, 'name' => $currentCategory[0]->name);
            $breadcrumb =  $this->buildShortcutBreadcrumb($client, $currentCategory[0]->parent_id, $breadcrumb);
        }
        return $breadcrumb;
    }
}