<?php
declare(strict_types=1);

/**
 * 路由定义
 * 注意：具体路由必须注册在通用路由（/{owner}/{repo}、/{username}）之前
 */

use App\Core\Router;

/** @var Router $router */

// ---------- 首页 / 探索 ----------
$router->get('/', 'HomeController@index');
$router->get('/explore', 'ExploreController@index');

// ---------- 认证 ----------
$router->get('/login', 'AuthController@loginForm');
$router->post('/login', 'AuthController@login');
$router->get('/register', 'AuthController@registerForm');
$router->post('/register', 'AuthController@register');
$router->post('/logout', 'AuthController@logout');

// ---------- 用户设置 ----------
$router->get('/settings', 'UserController@settingsGeneral');
$router->post('/settings/profile', 'UserController@updateProfile');
$router->post('/settings/password', 'UserController@updatePassword');
$router->get('/settings/ssh', 'UserController@sshKeys');
$router->post('/settings/ssh', 'UserController@addSshKey');
$router->post('/settings/ssh/delete', 'UserController@deleteSshKey');
$router->get('/settings/tokens', 'UserController@tokens');
$router->post('/settings/tokens', 'UserController@createToken');
$router->post('/settings/tokens/delete', 'UserController@deleteToken');

// ---------- 新建仓库 ----------
$router->get('/new', 'RepoController@newForm');
$router->post('/new', 'RepoController@create');

// ---------- 管理后台 ----------
$router->get('/admin', 'AdminController@dashboard');
$router->get('/admin/users', 'AdminController@users');
$router->post('/admin/users/{id:\d+}/ban', 'AdminController@banUser');
$router->post('/admin/users/{id:\d+}/unban', 'AdminController@unbanUser');
$router->post('/admin/users/{id:\d+}/promote', 'AdminController@promoteUser');
$router->post('/admin/users/{id:\d+}/demote', 'AdminController@demoteUser');
$router->get('/admin/repositories', 'AdminController@repositories');
$router->post('/admin/repositories/{id:\d+}/visibility', 'AdminController@toggleVisibility');
$router->post('/admin/repositories/{id:\d+}/delete', 'AdminController@deleteRepository');
$router->get('/admin/settings', 'AdminController@settingsForm');
$router->post('/admin/settings', 'AdminController@saveSettings');

// ---------- Git HTTP 智能协议（须早于通用仓库路由） ----------
$router->get('/{owner}/{repo}.git/info/refs', 'GitHttpController@infoRefs');
$router->post('/{owner}/{repo}.git/git-upload-pack', 'GitHttpController@uploadPack');
$router->post('/{owner}/{repo}.git/git-receive-pack', 'GitHttpController@receivePack');
$router->get('/{owner}/{repo}/info/refs', 'GitHttpController@infoRefs');
$router->post('/{owner}/{repo}/git-upload-pack', 'GitHttpController@uploadPack');
$router->post('/{owner}/{repo}/git-receive-pack', 'GitHttpController@receivePack');

// ---------- Issues ----------
$router->get('/{owner}/{repo}/issues', 'IssueController@index');
$router->get('/{owner}/{repo}/issues/new', 'IssueController@new');
$router->post('/{owner}/{repo}/issues', 'IssueController@store');
$router->get('/{owner}/{repo}/issues/{number:\d+}', 'IssueController@show');
$router->post('/{owner}/{repo}/issues/{number:\d+}/comment', 'IssueController@comment');
$router->post('/{owner}/{repo}/issues/{number:\d+}/state', 'IssueController@setState');
$router->post('/{owner}/{repo}/issues/{number:\d+}/labels', 'IssueController@setLabels');

// ---------- 仓库页面 ----------
$router->get('/{owner}/{repo}', 'RepoController@home');
$router->get('/{owner}/{repo}/tree/{rest*}', 'RepoController@tree');
$router->get('/{owner}/{repo}/blob/{rest*}', 'RepoController@blob');
$router->get('/{owner}/{repo}/raw/{rest*}', 'RepoController@raw');
$router->get('/{owner}/{repo}/commits', 'RepoController@commits');
$router->get('/{owner}/{repo}/commits/{ref*}', 'RepoController@commits');
$router->get('/{owner}/{repo}/commit/{sha:[0-9a-fA-F]{7,64}}', 'RepoController@commit');
$router->get('/{owner}/{repo}/branches', 'RepoController@branches');
$router->get('/{owner}/{repo}/tags', 'RepoController@tags');
$router->get('/{owner}/{repo}/compare', 'RepoController@compare');
$router->get('/{owner}/{repo}/releases', 'RepoController@releases');
$router->post('/{owner}/{repo}/releases', 'RepoController@createRelease');
$router->post('/{owner}/{repo}/releases/{id:\d+}/delete', 'RepoController@deleteRelease');
$router->get('/{owner}/{repo}/archive', 'RepoController@archive');
$router->post('/{owner}/{repo}/star', 'RepoController@star');
$router->post('/{owner}/{repo}/fork', 'RepoController@fork');

// ---------- 仓库设置 ----------
$router->get('/{owner}/{repo}/settings', 'RepoController@settingsGeneral');
$router->post('/{owner}/{repo}/settings/general', 'RepoController@updateGeneral');
$router->post('/{owner}/{repo}/settings/rename', 'RepoController@renameRepo');
$router->post('/{owner}/{repo}/settings/transfer', 'RepoController@transferRepo');
$router->post('/{owner}/{repo}/settings/delete', 'RepoController@deleteRepo');
$router->get('/{owner}/{repo}/settings/collaborators', 'RepoController@settingsCollaborators');
$router->post('/{owner}/{repo}/settings/collaborators', 'RepoController@addCollaborator');
$router->post('/{owner}/{repo}/settings/collaborators/remove', 'RepoController@removeCollaborator');

// ---------- 用户主页（兜底，必须放最后） ----------
$router->get('/{username}', 'UserController@profile');
