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

abstract class Controller extends FOSRestController
{

    protected $data = array();
    protected $parameters = array();

    protected $template;
    protected $format;
    protected $view;

    protected function getPaginator(Query $query, $page=1, $limit=10)
    {
        $paginator = new Pagerfanta(new DoctrineORMAdapter($query));
        $paginator->setMaxPerPage($limit);
        $paginator->setCurrentPage($page);

        return $paginator;
    }

    protected function getDoctrineManager($manager=null)
    {
        return $this->getDoctrine()->getManager($manager);
    }

    protected function getRepository($object, $manager=null)
    {

        return $this->getDoctrineManager()->getRepository(is_object($object) ? get_class($object) : $object);
    }

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
     * @param array $formArray A custom array of fields do be shown in the form
     * @return Form
     */
    protected function createFormArray($formArray)
    {
        $values = array();
        foreach($formArray as $value) {
            if(isset($value["default"])) {
                $values[$value["name"]] = $value["default"];
            }
        }
        $formBuilder = $this->createFormBuilder($values);
        foreach($formArray as $field) {
            $defaultoptions = array();
            if(isset($field['options'])) {
                $defaultoptions = $field['options'];
            }
            $formBuilder->add($field['name'], $field['type'], $defaultoptions);
        }

        return $formBuilder->getForm();
    }
    
    protected function addForm(Form $form, $name='form')
    {
        $this->addData($form->createView(), $name);
    }

    protected function addData($data, $key=null)
    {
        if($key)
            $this->data[$key] = $data;
        else
            $this->data[] = $data;
    }

    protected function removeData($offset)
    {
        unset($this->data[$offset]);
    }

    protected function hasData($offset)
    {
        return isset($this->data[$offset]);
    }

    protected function getData()
    {
        return $this->data;
    }

    protected function setTemplate($template)
    {
        $this->template = $template;
    }

    protected function getTemplate()
    {
        return $this->template;
    }

    protected function setFormat($format)
    {
        $this->format = $format;
    }

    protected function getFormat()
    {
        return $this->format;
    }

    protected function view($data = null, $statusCode = null, array $headers = array())
    {

        if(null !== $data){
            $this->data[] = $data;
        }

        $this->view = parent::view($this->data, $statusCode, $headers);

        if("" != $this->format){
            $this->view->setFormat($this->format);
        }

        if("" != $this->template){
            $this->view->setTemplate($this->template);
        }

        return $this->view;
    }

    protected function validate($object)
    {
        if (!$this->has('validator')) {
            $this->error('The validator is not registered in your application.');
        }

        return $this->get('validator')->validate($object);
    }

    protected function debug($object, $exit=true)
    {

        Debug::dump($object);
        if($exit)
            exit();
    }

    protected function persist($object, $flush=false, $manager=null)
    {
        if(null === $object){
            $this->error('No Null Update');
        }

        $this->getDoctrineManager($manager)->persist($object);
        if($flush){
            $this->flush($manager);
        }
    }

    protected function flush($manager=null)
    {

        $this->getDoctrineManager($manager)->flush();
    }

    protected function update($object, $manager=null)
    {
        $this->persist($object, true, $manager);
    }

    protected function merge($object, $manager=null)
    {
        
        return $this->getDoctrineManager($manager)->merge($object);
    }

    protected function detach($object, $manager=null)
    {
        
        return $this->getDoctrineManager($manager)->detach($object);
    }

    protected function remove($object, $flush=false,$manager=null)
    {

        if(null === $object){
            $this->error('no null update');
        }

        $this->getDoctrineManager($manager)->remove($object);
        if($flush){
            $this->flush($manager);
        }
    }

    protected function dispatch($eventName, Event $event = null)
    {

        return $this->getDispatcher()->dispatch($eventName, $event);

    }

    protected function NotFound($message="Error 404"){

        throw $this->createNotFoundException($message);

    }

    protected function NotFoundUnless($condition, $message = 'Error 404')
    {
        if (!$condition)
        {
            $this->NotFound($message);
        }
    }

    protected function NotFoundIf($condition, $message = 'Error 404')
    {
        if ($condition)
        {
            $this->NotFound($message);
        }
    }

    protected function AccessDenied($message='Unable to access this page!')
    {

        throw $this->createAccessDeniedException($message);

    }

    protected function AccessDeniedIf($condition, $message='Unable to access this page!')
    {

        if ($condition)
        {
            $this->AccessDenied($message);
        }

    }

    protected function AccessDeniedUnless($condition, $message='Unable to access this page!')
    {

        if (!$condition)
        {
            $this->AccessDenied($message);
        }

    }

    protected function error($message='error in your application!')
    {

        throw new \LogicException($message);

    }

    protected function handleForm(Form $form, Request $request)
    {
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            return true;
        }
    }

    protected function setParameters(){

        $attributes = $this->getRequest()->attributes->all();
        if($attributes['paramFetcher']) {
            $this->parameters = array_merge($attributes, $attributes['paramFetcher']->all());
            unset($this->parameters['paramFetcher']);
        }

    }

    protected function locateResource($name, $dir = null, $first = true)
    {
        
        $path = null;
        
        try {

            $path = $this->kernel()->locateResource($name, $dir, $first);
            
        } catch (\Exception $e) {

            return;
        }

        return $path;

    }

    protected function kernel()
    {
        if (!$this->has('kernel')) {
            $this->error('The kernel is not registered in your application.');
        }

        return $this->get('kernel');
    }
    
    protected function getRootDir()
    {
        if (!$this->has('kernel')) {
            $this->error('The kernel is not registered in your application.');
        }

        return $this->kernel()->getRootDir();
    }

    protected function session()
    {
        if (!$this->has('session')) {
            $this->error('The session is not registered in your application.');
        }

        return $this->get('session');
    }

    protected function mailer()
    {
        if (!$this->has('mailer')) {
            $this->error('The mailer is not registered in your application.');
        }

        return $this->get('mailer');
    }

    protected function security()
    {
        if (!$this->has('security.context')) {
            $this->error('The security.context is not registered in your application.');
        }

        return $this->get('security.context');
    }

    protected function memcache()
    {
        if (!$this->has('memcache.default')) {
            $this->error('The memcache.default is not registered in your application.');
        }

        return $this->get('memcache.default');
    }

    protected function getmemcache($key)
    {
        return $this->memcache()->get($key);
    }

    protected function setmemcache($key, $data, $time = 86400)
    {
        return $this->memcache()->set($key, $datacache, $time);
    }
    
    protected function addflash($message, $type="notice")
    {
        
        return $this->session()->getFlashBag()->add($type, $message);
    }
    
}
