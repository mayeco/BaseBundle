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

class Controller extends FOSRestController
{

    protected $data = array();
    protected $parameters = array();

    protected $template;
    protected $format;
    protected $view;

    protected function getPaginator(Query $query, $page, $limit)
    {
        $paginator = new Pagerfanta(new DoctrineORMAdapter($query));
        $paginator->setCurrentPage($page);
        $paginator->setMaxPerPage($limit);

        return $paginator;
    }

    protected function getDoctrineManager($manager=null)
    {
        return $this->getDoctrine()->getManager($manager);
    }

    protected function getRepository($class, $manager=null)
    {

        return $this->getDoctrineManager($manager)->getRepository($class);
    }

    protected function getDispatcher()
    {
        if (!$this->has('event_dispatcher')) {
            $this->error('The event_dispatcher is not registered in your application.');
        }

        return $this->get('event_dispatcher');
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

    public function hasData($offset)
    {
        return isset($this->data[$offset]);
    }

    public function getData()
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

        if($this->template){
            $this->view->setTemplate($this->template);
        } else {
            throw new \InvalidArgumentException('No template');
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

    public function NotFoundUnless($condition, $message = 'Error 404')
    {
        if (!$condition)
        {
            $this->NotFound($message);
        }
    }

    public function NotFoundIf($condition, $message = 'Error 404')
    {
        if ($condition)
        {
            $this->NotFound($message);
        }
    }

    public function AccessDenied($message='Unable to access this page!')
    {

        throw $this->createAccessDeniedException($message);

    }

    public function AccessDeniedIf($condition, $message='Unable to access this page!')
    {

        if ($condition)
        {
            $this->AccessDenied($message);
        }

    }

    public function AccessDeniedUnless($condition, $message='Unable to access this page!')
    {

        if (!$condition)
        {
            $this->AccessDenied($message);
        }

    }

    public function error($message='error in your application!')
    {

        throw new \LogicException($message);

    }

    public function handleForm(Form $form, Request $request = null)
    {
        if(null === $request){
            $request = $this->getRequest();
        }

        if(!$form->isSubmitted()) {
            $form->handleRequest($request);
        }

        if ($form->isValid()) {
            return true;
        }

        return false;
    }

    public function setParameters(){

        $attributes = $this->getRequest()->attributes->all();
        if($attributes['paramFetcher']) {
            $this->parameters = array_merge($attributes, $attributes['paramFetcher']->all());
            unset($this->parameters['paramFetcher']);
        }

    }

    public function getRootDir()
    {
        if (!$this->has('kernel')) {
            $this->error('The kernel is not registered in your application.');
        }

        return $this->get('kernel')->getRootDir();
    }

    public function session()
    {
        if (!$this->has('session')) {
            $this->error('The session is not registered in your application.');
        }

        return $this->get('session');
    }
    
    public function addflash($message, $type="notice")
    {
        
        return $this->session()->getFlashBag()->add($type, $message);
    }
    
}
