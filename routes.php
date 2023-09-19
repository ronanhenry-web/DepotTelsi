// -----------------------------------------------------------------------------------------------------------------------------------
// QualificationsManager
// -----------------------------------------------------------------------------------------------------------------------------------

$app->group('/qualificationsmanager', function () use ($app) {
    $app->get('[/]', Taas\TaasInterface\Controller\QualificationsManagerInterfaceController::class . ':index')->setName('interface_qualificationsmanager_index');

    //Affichage de l'interface de paramétrage générale des catégories
    $app->get('/categoriesManager', Taas\TaasInterface\Controller\QualificationsManagerInterfaceController::class . ':displayAdminTableCategories')->setName('interface_qualificationsmanager_admin');
    //Permet de récupérer uniquement les services du même groupe que la catégorie kiamo qui est la catégorie parente de celle du TaaS
    $app->get('/getCategoryServices/{categoryId}', Taas\TaasInterface\Controller\QualificationsManagerInterfaceController::class . ':getCategoryCrcServices')->setName('interface_qualificationsmanager_get_category_services');
    //Permet de récupérer uniquement les qualifications appartenant à la catégorie kiamo qui est la catégorie parente de celle du TaaS
    $app->get('/getCategoryQualifications/{kiamoCategoryId}/{taasCategoryId}', Taas\TaasInterface\Controller\QualificationsManagerInterfaceController::class . ':getCategoryCrcQualifications')->setName('interface_qualificationsmanager_get_category_qualifications');
    //Ajout de qualification au sein d'une catégorie
    $app->post('/addQualifsToCategory', Taas\TaasInterface\Controller\QualificationsManagerInterfaceController::class . ':addQualifsToCategory')->setName('interface_qualificationsmanager_add_qualifs_to_category');

    // Actions CUD
    $app->post('/createCategory', Taas\TaasInterface\Controller\QualificationsManagerInterfaceController::class . ':createCategory')->setName('interface_qualificationsmanager_create');
    $app->post('/deleteCategory', Taas\TaasInterface\Controller\QualificationsManagerInterfaceController::class . ':deleteCategory')->setName('interface_qualificationsmanager_delete');
    $app->post('/updateCategory', Taas\TaasInterface\Controller\QualificationsManagerInterfaceController::class . ':updateCategory')->setName('interface_qualificationsmanager_update');

    //Apperçu de la vue agent
    $app->get('/previewAgentView', Taas\TaasInterface\Controller\QualificationsManagerInterfaceController::class . ':previewAgentView')->setName('interface_qualificationsmanager_agent_preview');
});