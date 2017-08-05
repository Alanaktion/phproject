<?php

$fw = App::fw();

// Index
$fw->route('GET /', 'Controller\Index->index');
$fw->route('GET /login', 'Controller\Index->login');
$fw->route('GET|POST /reset', 'Controller\Index->reset');
$fw->route('GET|POST /reset/forced', 'Controller\Index->reset_forced');
$fw->route('GET|POST /reset/@token', 'Controller\Index->reset_complete');
$fw->route('POST /login', 'Controller\Index->loginpost');
$fw->route('POST /register', 'Controller\Index->registerpost');
$fw->route('GET|POST /logout', 'Controller\Index->logout');
$fw->route('GET|POST /ping', 'Controller\Index->ping');
$fw->route('GET /opensearch.xml', 'Controller\Index->opensearch');

// Style
$fw->route('GET /style/@timestamp/@file.css', 'Controller\Style->index', 86400 * 90);

// Issues
$fw->route('GET /issues', 'Controller\Issues->index');
$fw->route('GET /issues/new', 'Controller\Issues->add_selecttype');
$fw->route('GET /issues/new/@type', 'Controller\Issues->add');
$fw->route('GET /issues/new/@type/@parent', 'Controller\Issues->add');
$fw->route('GET /issues/edit/@id', 'Controller\Issues->edit');
$fw->route('POST /issues/save', 'Controller\Issues->save');
$fw->route('POST /issues/bulk_update', 'Controller\Issues->bulk_update');
$fw->route('GET /issues/export', 'Controller\Issues->export');
$fw->route('GET|POST /issues/@id', 'Controller\Issues->single');
$fw->route('GET|POST /issues/delete/@id', 'Controller\Issues->single_delete');
$fw->route('GET|POST /issues/undelete/@id', 'Controller\Issues->single_undelete');
$fw->route('POST /issues/comment/save', 'Controller\Issues->comment_save');
$fw->route('POST /issues/comment/delete', 'Controller\Issues->comment_delete');
$fw->route('POST /issues/file/delete', 'Controller\Issues->file_delete');
$fw->route('POST /issues/file/undelete', 'Controller\Issues->file_undelete');
$fw->route('GET /issues/@id/history', 'Controller\Issues->single_history');
$fw->route('GET /issues/@id/related', 'Controller\Issues->single_related');
$fw->route('GET /issues/@id/dependencies', 'Controller\Issues->single_dependencies');
$fw->route('POST /issues/@id/dependencies', 'Controller\Issues->add_dependency');
$fw->route('POST /issues/@id/dependencies/delete', 'Controller\Issues->delete_dependency');
$fw->route('GET /issues/@id/watchers', 'Controller\Issues->single_watchers');
$fw->route('POST /issues/@id/watchers', 'Controller\Issues->add_watcher');
$fw->route('POST /issues/@id/watchers/delete', 'Controller\Issues->delete_watcher');
$fw->route('GET /issues/project/@id', 'Controller\Issues->project_overview');
$fw->route('GET /search', 'Controller\Issues->search');
$fw->route('POST /issues/upload', 'Controller\Issues->upload');
$fw->route('GET /issues/close/@id', 'Controller\Issues->close');
$fw->route('GET /issues/reopen/@id', 'Controller\Issues->reopen');
$fw->route('GET /issues/copy/@id', 'Controller\Issues->copy');
$fw->route('GET /issues/parent_ajax', 'Controller\Issues->parent_ajax');

// Tags
$fw->route('GET /tag', 'Controller\Tag->index');
$fw->route('GET /tag/@tag', 'Controller\Tag->single');

// User pages
$fw->route('GET /user', 'Controller\User->account');
$fw->route('POST /user', 'Controller\User->save');
$fw->route('POST /user/avatar', 'Controller\User->avatar');
$fw->route('GET /user/dashboard', 'Controller\User->dashboard');
$fw->route('POST /user/dashboard', 'Controller\User->dashboardPost');
$fw->route('GET /user/@username', 'Controller\User->single');
$fw->route('GET /user/@username/tree', 'Controller\User->single_tree');

// Feeds
$fw->route('GET /atom.xml', 'Controller\Index->atom');

// Administration
$fw->route('GET|POST /admin', 'Controller\Admin->index');
$fw->route('GET /admin/@tab', 'Controller\Admin->@tab');

$fw->route('POST /admin/config/saveattribute', 'Controller\Admin->config_post_saveattribute');

$fw->route('GET|POST /admin/plugins/@id', 'Controller\Admin->plugin_single');

$fw->route('GET /admin/users/deleted', 'Controller\Admin->deleted_users');
$fw->route('GET /admin/users/new', 'Controller\Admin->user_new');
$fw->route('POST /admin/users/save', 'Controller\Admin->user_save');
$fw->route('GET /admin/users/@id', 'Controller\Admin->user_edit');
$fw->route('GET|POST /admin/users/@id/delete', 'Controller\Admin->user_delete');
$fw->route('GET|POST /admin/users/@id/undelete', 'Controller\Admin->user_undelete');

$fw->route('POST /admin/groups/new', 'Controller\Admin->group_new');
$fw->route('GET|POST /admin/groups/@id', 'Controller\Admin->group_edit');
$fw->route('GET|POST /admin/groups/@id/delete', 'Controller\Admin->group_delete');
$fw->route('POST /admin/groups/ajax', 'Controller\Admin->group_ajax');
$fw->route('GET|POST /admin/groups/@id/setmanager/@user_group_id', 'Controller\Admin->group_setmanager');

$fw->route('GET|POST /admin/attributes/new', 'Controller\Admin->attribute_new');
$fw->route('GET|POST /admin/attributes/@id', 'Controller\Admin->attribute_edit');
$fw->route('GET|POST /admin/attributes/@id/delete', 'Controller\Admin->attribute_delete');

$fw->route('GET|POST /admin/sprints/new', 'Controller\Admin->sprint_new');
$fw->route('GET|POST /admin/sprints/@id', 'Controller\Admin->sprint_edit');

// Taskboard
$fw->route('GET /taskboard', 'Controller\Taskboard->index');
$fw->route('GET /taskboard/@id', 'Controller\Taskboard->index');
$fw->route('GET /taskboard/@id/@filter', 'Controller\Taskboard->index');
$fw->route('GET /taskboard/@id/burndown/@filter', 'Controller\Taskboard->burndown');
$fw->route('GET /taskboard/@id/burndownPrecise/@filter', 'Controller\Taskboard->burndownPrecise');
$fw->route('POST /taskboard/add', 'Controller\Taskboard->add');
$fw->route('POST /taskboard/edit/@id', 'Controller\Taskboard->edit');
$fw->route('POST /taskboard/saveManHours', 'Controller\Taskboard->saveManHours');

// Backlog
$fw->route('GET /backlog', 'Controller\Backlog->index');
$fw->route('GET /backlog/old', 'Controller\Backlog->index_old');
$fw->route('POST /backlog/edit', 'Controller\Backlog->edit');
$fw->route('POST /backlog/sort', 'Controller\Backlog->sort');
$fw->route('GET /backlog/@filter', 'Controller\Backlog->redirect');
$fw->route('GET /backlog/@filter/@groupid', 'Controller\Backlog->redirect');

// Files
$fw->route('GET /files/thumb/@size-@id.@format', 'Controller\Files->thumb');
$fw->route('GET /files/preview/@id', 'Controller\Files->preview');
$fw->route('GET /files/@id/@name', 'Controller\Files->file');
$fw->route('GET /avatar/@size-@id.@format', 'Controller\Files->avatar');

// REST API
$fw->route('GET /issues.json', 'Controller\Api\Issues->get');
$fw->route('POST /issues.json', 'Controller\Api\Issues->post');
$fw->route('GET /issues/@id.json', 'Controller\Api\Issues->single_get');
$fw->route('PUT /issues/@id.json', 'Controller\Api\Issues->single_put');
$fw->route('DELETE /issues/@id.json', 'Controller\Api\Issues->single_delete');
$fw->route('GET /issues/@id/comments.json', 'Controller\Api\Issues->single_comments');
$fw->route('POST /issues/@id/comments.json', 'Controller\Api\Issues->single_comments_post');
$fw->route('GET /issues/types.json', 'Controller\Api\Issues->types');
$fw->route('GET /tag.json', 'Controller\Api\Issues->tag');
$fw->route('GET /tag/@tag.json', 'Controller\Api\Issues->tag_single');
$fw->route('GET /sprints.json', 'Controller\Api\Issues->sprints');
$fw->route('GET /sprints/old.json', 'Controller\Api\Issues->sprints_old');
$fw->route('GET /user/@username.json', 'Controller\Api\User->single_get');
$fw->route('GET /useremail/@email.json', 'Controller\Api\User->single_email');
$fw->route('GET /user.json', 'Controller\Api\User->get');
$fw->route('GET /usergroups.json', 'Controller\Api\User->get_group');

// Set up error handling
$fw->set("ONERROR", function (Base $fw) {
    if ($fw->get("AJAX")) {
        if (!headers_sent()) {
            header("Content-type: application/json");
        }
        echo json_encode(array(
            "error" => $fw->get("ERROR.title"),
            "text" => $fw->get("ERROR.text")
        ));
    } else {
        switch ($fw->get("ERROR.code")) {
            case 404:
                $fw->set("title", "Not Found");
                $fw->set("ESCAPE", false);
                echo \Helper\View::instance()->render("error/404.html");
                break;
            case 403:
                echo "You do not have access to this page.";
                break;
            default:
                // Pass unhandled errors back to framework
                return false;
        }
    }
});
