<?php

namespace Mayeco\BaseBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Form\Form;

use Doctrine\ORM\Query;
use Doctrine\Common\Util\Debug;

use FOS\RestBundle\Request\ParamFetcher;
use FOS\RestBundle\Controller\FOSRestController;

use Pagerfanta\Pagerfanta;
use Pagerfanta\Adapter\DoctrineORMAdapter;

/**
 * Class Controller
 * @package Mayeco\BaseBundle
 */
abstract class MayecoController extends FOSRestController
{

    /**
     * @var array
     */
    protected $data = array();
    
    /**
     * @var array
     */
    protected $parameters = array();

    /**
     * @var string
     */
    protected $template = null;
    
    /**
     * @var string
     */
    protected $format = null;
    
    /**
     * @var
     */
    protected $view = null;

    /**
     * @param Query $query
     * @param int $page
     * @param int $limit
     * @return Pagerfanta
     */
    protected function getPaginator(Query $query, $page = 1, $limit = 10)
    {
        $paginator = new Pagerfanta(new DoctrineORMAdapter($query));
        $paginator->setMaxPerPage($limit);
        $paginator->setCurrentPage($page);

        return $paginator;
    }

    /**
     * @param null $manager
     * @return mixed
     */
    protected function getDoctrineManager($manager = null)
    {
        return $this->getDoctrine()->getManager($manager);
    }

    /**
     * @param $object
     * @param null $manager
     * @return mixed
     */
    protected function getRepository($object, $manager = null)
    {
        return $this->getDoctrineManager($manager)->getRepository(is_object($object) ? get_class($object) : $object);
    }

    /**
     * @return mixed
     */
    protected function getDispatcher()
    {
        if (!$this->has('event_dispatcher')) {
            $this->error('The event_dispatcher is not registered in your application.');
        }

        return $this->get('event_dispatcher');
    }

    /**
     * Create a form from an array.
     *
     * @param array $formArray
     * @return Form
     */
    protected function createFormArray($formArray)
    {
        $values = array();
        foreach ($formArray as $value) {
            if (isset($value["default"])) {
                $values[$value["name"]] = $value["default"];
            }
        }
        
        $formBuilder = $this->createFormBuilder($values);
        foreach ($formArray as $field) {
            $defaultoptions = array();
            if (isset($field['options'])) {
                $defaultoptions = $field['options'];
            }
            $formBuilder->add($field['name'], $field['type'], $defaultoptions);
        }

        return $formBuilder->getForm();
    }

    /**
     * @param Form $form
     * @param string $name
     */
    protected function addForm(Form $form, $name = 'form')
    {
        $this->addData($form->createView(), $name);
    }

    /**
     * @param $data
     * @param null $key
     */
    protected function addData($data, $key = null)
    {
        if ($key) {
            $this->data[$key] = $data;
        } else {
            $this->data[] = $data;
        }
    }

    /**
     * @param $offset
     */
    protected function removeData($offset)
    {
        unset($this->data[$offset]);
    }

    /**
     * @param $offset
     * @return bool
     */
    protected function hasData($offset)
    {
        return isset($this->data[$offset]);
    }

    /**
     * @return array
     */
    protected function getData()
    {
        return $this->data;
    }

    /**
     * @param $template
     */
    protected function setTemplate($template)
    {
        $this->template = $template;
    }

    /**
     * @return mixed
     */
    protected function getTemplate()
    {
        return $this->template;
    }

    /**
     * @param $format
     */
    protected function setFormat($format)
    {
        $this->format = $format;
    }

    /**
     * @return mixed
     */
    protected function getFormat()
    {
        return $this->format;
    }

    /**
     * @param null $data
     * @param null $statusCode
     * @param array $headers
     * @return mixed
     */
    protected function view($data = null, $statusCode = null, array $headers = array())
    {
        if (null !== $data) {
            $this->data[] = $data;
        }

        $this->view = parent::view($this->data, $statusCode, $headers);

        if ("" != $this->format) {
            $this->view->setFormat($this->format);
        }

        if ("" != $this->template) {
            $this->view->setTemplate($this->template);
        }

        return $this->view;
    }

    /**
     * @param $object
     * @return mixed
     */
    protected function validate($object)
    {
        if (!$this->has('validator')) {
            $this->error('The validator is not registered in your application.');
        }

        return $this->get('validator')->validate($object);
    }

    /**
     * @param $object
     * @param bool $exit
     */
    protected function debug($object, $exit = true)
    {
        Debug::dump($object);
        if ($exit) {
            exit();
        }
    }

    /**
     * @param $object
     * @param bool $flush
     * @param null $manager
     */
    protected function persist($object, $flush = false, $manager = null)
    {
        if (null === $object) {
            $this->error('No Null Update');
        }

        $this->getDoctrineManager($manager)->persist($object);
        if ($flush) {
            $this->flush($manager);
        }
    }

    /**
     * @param null $manager
     */
    protected function flush($manager = null)
    {
        $this->getDoctrineManager($manager)->flush();
    }

    /**
     * @param $object
     * @param null $manager
     */
    protected function update($object, $manager = null)
    {
        $this->persist($object, true, $manager);
    }

    /**
     * @param $object
     * @param null $manager
     * @return mixed
     */
    protected function merge($object, $manager = null)
    {
        return $this->getDoctrineManager($manager)->merge($object);
    }

    /**
     * @param $object
     * @param null $manager
     * @return mixed
     */
    protected function detach($object, $manager = null)
    {
        return $this->getDoctrineManager($manager)->detach($object);
    }

    /**
     * @param $object
     * @param bool $flush
     * @param null $manager
     */
    protected function remove($object, $flush = false, $manager = null)
    {
        if (null === $object) {
            $this->error('No null update');
        }

        $this->getDoctrineManager($manager)->remove($object);
        if ($flush) {
            $this->flush($manager);
        }
    }

    /**
     * @param $eventName
     * @param Event $event
     * @return mixed
     */
    protected function dispatch($eventName, Event $event = null)
    {
        return $this->getDispatcher()->dispatch($eventName, $event);
    }

    /**
     * @param string $message
     */
    protected function NotFound($message = "Error 404")
    {
        throw $this->createNotFoundException($message);
    }

    /**
     * @param $condition
     * @param string $message
     */
    protected function NotFoundUnless($condition, $message = 'Error 404')
    {
        if (!$condition) {
            $this->NotFound($message);
        }
    }

    /**
     * @param $condition
     * @param string $message
     */
    protected function NotFoundIf($condition, $message = 'Error 404')
    {
        if ($condition) {
            $this->NotFound($message);
        }
    }

    /**
     * @param string $message
     */
    protected function AccessDenied($message = 'Unable to access this page!')
    {
        throw $this->createAccessDeniedException($message);
    }

    /**
     * @param $condition
     * @param string $message
     */
    protected function AccessDeniedIf($condition, $message = 'Unable to access this page!')
    {
        if ($condition) {
            $this->AccessDenied($message);
        }
    }

    /**
     * @param $condition
     * @param string $message
     */
    protected function AccessDeniedUnless($condition, $message = 'Unable to access this page!')
    {
        if (!$condition) {
            $this->AccessDenied($message);
        }
    }

    /**
     * @param string $message
     */
    protected function error($message = 'Error in your application!')
    {
        throw new \LogicException($message);
    }

    /**
     * @param Form $form
     * @param Request $request
     * @return bool
     */
    protected function handleForm(Form $form, Request $request)
    {
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            return true;
        }
    }

    /**
     *
     */
    protected function setParameters()
    {
        $attributes = $this->getRequest()->attributes->all();
        if ($attributes['paramFetcher']) {
            $this->parameters = array_merge($attributes, $attributes['paramFetcher']->all());
            unset($this->parameters['paramFetcher']);
        }
    }

    /**
     * @param $name
     * @param null $dir
     * @param bool $first
     * @return null|void
     */
    protected function getFilename($name, $dir = null, $first = true)
    {
        $path = null;
        try {
            $path = $this->kernel()->locateResource($name, $dir, $first);
        } catch (\Exception $e) {
            
            return;
        }

        return $path;
    }

    /**
     * @return mixed
     */
    protected function kernel()
    {
        if (!$this->has('kernel')) {
            $this->error('The kernel is not registered in your application.');
        }

        return $this->get('kernel');
    }

    /**
     * @return mixed
     */
    protected function getRootDir()
    {
        return $this->kernel()->getRootDir();
    }

    /**
     * @return mixed
     */
    protected function session()
    {
        if (!$this->has('session')) {
            $this->error('The session is not registered in your application.');
        }

        return $this->get('session');
    }

    /**
     * @param $key
     * @return mixed
     */
    protected function getsession($key)
    {
        return $this->session()->get($key);
    }

    /**
     * @param $key
     * @param $data
     * @return mixed
     */
    protected function setsession($key, $data)
    {
        return $this->session()->set($key, $data);
    }

    /**
     * @return mixed
     */
    protected function mailer()
    {
        if (!$this->has('mailer')) {
            $this->error('The mailer is not registered in your application.');
        }

        return $this->get('mailer');
    }

    /**
     * @return mixed
     */
    protected function security()
    {
        if (!$this->has('security.context')) {
            $this->error('The security.context is not registered in your application.');
        }

        return $this->get('security.context');
    }

    /**
     * @return mixed
     */
    protected function memcache()
    {
        if (!$this->has('memcache.default')) {
            $this->error('The memcache.default is not registered in your application.');
        }

        return $this->get('memcache.default');
    }

    /**
     * @param $key
     * @return mixed
     */
    protected function getmemcache($key)
    {
        return $this->memcache()->get($key);
    }

    /**
     * @param $key
     * @param $data
     * @param int $time
     * @return mixed
     */
    protected function setmemcache($key, $data, $time = 86400)
    {
        return $this->memcache()->set($key, $datacache, $time);
    }

    /**
     * @param $message
     * @param string $type
     * @return mixed
     */
    protected function addflash($message, $type = "notice")
    {
        return $this->session()->getFlashBag()->add($type, $message);
    }
    
}