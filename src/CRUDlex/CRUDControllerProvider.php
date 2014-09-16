<?php

/*
 * This file is part of the CRUDlex package.
 *
 * (c) Philip Lehmann-Böhm <philip@philiplb.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CRUDlex;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Response;
use CRUDlex\CRUDEntity;

class CRUDControllerProvider implements ControllerProviderInterface {

    protected function getNotFoundPage($app, $error) {
        return new Response($app['twig']->render('@crud/notFound.twig', array(
            'error' => $error,
            'crudEntity' => '',
            'layout' => $app['crud.layout']
        )), 404);
    }

    protected function getLayout($app, $action, $entity) {
        if ($app->offsetExists('crud.layout.'.$action.'.'.$entity)) {
            return $app['crud.layout.'.$action.'.'.$entity];
        }
        if ($app->offsetExists('crud.layout.'.$entity)) {
            return $app['crud.layout.'.$entity];
        }
        if ($app->offsetExists('crud.layout.'.$action)) {
            return $app['crud.layout.'.$action];
        }
        return $app['crud.layout'];
    }

    public function connect(Application $app) {
        if ($app->offsetExists('twig.loader.filesystem')) {
            $app['twig.loader.filesystem']->addPath(__DIR__ . '/../views/', 'crud');
        }

        if (!$app->offsetExists('crud.layout')) {
            $app['crud.layout'] = '@crud/layout.twig';
        }

        $factory = $app['controllers_factory'];
        $factory->match('/{entity}/create', 'CRUDlex\CRUDControllerProvider::create')
                ->bind('crudCreate');
        $factory->match('/{entity}', 'CRUDlex\CRUDControllerProvider::showList')
                ->bind('crudList');
        $factory->match('/{entity}/{id}', 'CRUDlex\CRUDControllerProvider::show')
                ->bind('crudShow');
        $factory->match('/{entity}/{id}/edit', 'CRUDlex\CRUDControllerProvider::edit')
                ->bind('crudEdit');
        $factory->post('/{entity}/{id}/delete', 'CRUDlex\CRUDControllerProvider::delete')
                ->bind('crudDelete');
        $factory->post('/{entity}/{id}/{field}/delete', 'CRUDlex\CRUDControllerProvider::deleteFile')
                ->bind('crudDeleteFile');
        return $factory;
    }

    public function create(Application $app, $entity) {
        $crudData = $app['crud']->getData($entity);
        if (!$crudData) {
            return $this->getNotFoundPage($app, $app['crud']->translate('entityNotFound'));
        }

        $errors = array();
        $instance = $crudData->createEmpty();
        $definition = $crudData->getDefinition();
        $fields = $definition->getEditableFieldNames();

        if ($app['request']->getMethod() == 'POST') {
            foreach ($fields as $field) {
                if ($definition->getType($field) == 'file') {
                    $file = $app['request']->files->get($field);
                    if ($file) {
                        $instance->set($field, $file->getClientOriginalName());
                    }
                } else {
                    $instance->set($field, $app['request']->get($field));
                }
            }
            $validation = $instance->validate($crudData);
            if (!$validation['valid']) {
                $errors = $validation['errors'];
                $app['session']->getFlashBag()->add('danger', $app['crud']->translate('create.error'));
            } else {
                $crudData->create($instance);
                $id = $instance->get('id');
                $crudData->storeFiles($app['request'], $entity, $instance);

                $app['session']->getFlashBag()->add('success', $app['crud']->translate('create.success', array($crudData->getDefinition()->getLabel(), $id)));
                return $app->redirect($app['url_generator']->generate('crudShow', array('entity' => $entity, 'id' => $id)));
            }
        }

        $definition = $crudData->getDefinition();

        return $app['twig']->render('@crud/form.twig', array(
            'crudEntity' => $entity,
            'crudData' => $crudData,
            'entity' => $instance,
            'mode' => 'create',
            'errors' => $errors,
            'layout' => $this->getLayout($app, 'create', $entity)
        ));
    }

    public function showList(Application $app, $entity) {
        $crudData = $app['crud']->getData($entity);
        if (!$crudData) {
            return $this->getNotFoundPage($app, $app['crud']->translate('entityNotFound'));
        }
        $entitiesRaw = $crudData->listEntries();
        $entities = array();
        foreach ($entitiesRaw as $curEntity) {
            $crudData->fetchReferences($curEntity);
            $entities[] = $curEntity;
        }
        $definition = $crudData->getDefinition();
        return $app['twig']->render('@crud/list.twig', array(
            'crudEntity' => $entity,
            'definition' => $definition,
            'entities' => $entities,
            'layout' => $this->getLayout($app, 'list', $entity)
        ));
    }

    public function show(Application $app, $entity, $id) {
        $crudData = $app['crud']->getData($entity);
        if (!$crudData) {
            return $this->getNotFoundPage($app, $app['crud']->translate('entityNotFound'));
        }
        $instance = $crudData->get($id);
        $crudData->fetchReferences($instance);
        if (!$instance) {
            return $this->getNotFoundPage($app, $app['crud']->translate('instanceNotFound'));
        }
        $definition = $crudData->getDefinition();
        return $app['twig']->render('@crud/show.twig', array(
            'crudEntity' => $entity,
            'entity' => $instance,
            'layout' => $this->getLayout($app, 'show', $entity)
        ));
    }

    public function edit(Application $app, $entity, $id) {
        $crudData = $app['crud']->getData($entity);
        if (!$crudData) {
            return $this->getNotFoundPage($app, $app['crud']->translate('entityNotFound'));
        }
        $instance = $crudData->get($id);
        if (!$instance) {
            return $this->getNotFoundPage($app, $app['crud']->translate('instanceNotFound'));
        }

        $definition = $crudData->getDefinition();

        $errors = array();
        $fields = $definition->getEditableFieldNames();


        if ($app['request']->getMethod() == 'POST') {
            foreach ($fields as $field) {
                if ($definition->getType($field) == 'file') {
                    $file = $app['request']->files->get($field);
                    if ($file) {
                        $instance->set($field, $file->getClientOriginalName());
                    }
                } else {
                    $instance->set($field, $app['request']->get($field));
                }
            }
            $validation = $instance->validate($crudData);
            if (!$validation['valid']) {
                $app['session']->getFlashBag()->add('danger', $app['crud']->translate('edit.error'));
                $errors = $validation['errors'];
            } else {
                $crudData->update($instance);
                $crudData->storeFiles($app['request'], $entity, $instance);
                $app['session']->getFlashBag()->add('success', $app['crud']->translate('edit.success', array($crudData->getDefinition()->getLabel(), $id)));
                return $app->redirect($app['url_generator']->generate('crudShow', array('entity' => $entity, 'id' => $id)));
            }
        }

        return $app['twig']->render('@crud/form.twig', array(
            'crudEntity' => $entity,
            'crudData' => $crudData,
            'entity' => $instance,
            'mode' => 'edit',
            'errors' => $errors,
            'layout' => $this->getLayout($app, 'edit', $entity)
        ));
    }

    public function delete(Application $app, $entity, $id) {
        $crudData = $app['crud']->getData($entity);
        if (!$crudData) {
            return $this->getNotFoundPage($app, $app['crud']->translate('entityNotFound'));
        }
        $instance = $crudData->get($id);
        if (!$instance) {
            return $this->getNotFoundPage($app, $app['crud']->translate('instanceNotFound'));
        }

        $deleted = $crudData->delete($id);
        if ($deleted) {
            $app['session']->getFlashBag()->add('success', $app['crud']->translate('delete.success', array($crudData->getDefinition()->getLabel())));
            return $app->redirect($app['url_generator']->generate('crudList', array('entity' => $entity)));
        } else {
            $app['session']->getFlashBag()->add('danger', $app['crud']->translate('delete.error', array($crudData->getDefinition()->getLabel())));
            return $app->redirect($app['url_generator']->generate('crudShow', array('entity' => $entity, 'id' => $id)));
        }
    }

    public function deleteFile(Application $app, $entity, $id, $field) {
        $crudData = $app['crud']->getData($entity);
        if (!$crudData) {
            return $this->getNotFoundPage($app, $app['crud']->translate('entityNotFound'));
        }
        $instance = $crudData->get($id);
        if (!$instance) {
            return $this->getNotFoundPage($app, $app['crud']->translate('instanceNotFound'));
        }
        $crudData->deleteFile($instance, $entity, $field);
        $app['session']->getFlashBag()->add('success', $app['crud']->translate('file.deleted'));
        return $app->redirect($app['url_generator']->generate('crudShow', array('entity' => $entity, 'id' => $id)));
    }
}
