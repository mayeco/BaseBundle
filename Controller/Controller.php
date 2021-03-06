<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * MIT license.
 */
 
namespace Mayeco\BaseBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Form\Form;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;

use Doctrine\ORM\Query;

use FOS\RestBundle\Request\ParamFetcher;
use FOS\RestBundle\Controller\FOSRestController;

use Pagerfanta\Pagerfanta;
use Pagerfanta\Adapter\DoctrineORMAdapter;

use Webmozart\PathUtil\Path;

/**
 * Class MayecoController
 * @package Mayeco\BaseBundle
 */
abstract class Controller extends FOSRestController
{

    /**
     * @var array
     */
    private $data = array();

    /**
     * @var string
     */
    private $template = null;
    
    /**
     * @var string
     */
    private $format = null;
    
    /**
     * @var
     */
    private $view = null;
    
    /**
     * @var
     */
    private $expression;

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
     * Shortcut to return the Doctrine Registry service.
     *
     * @return Registry
     *
     * @throws \LogicException If DoctrineBundle is not available
     */
    public function getDoctrineMongo()
    {
        if (!$this->container->has('doctrine_mongodb')) {
            throw new \LogicException('The DoctrineMongoBundle is not registered in your application.');
        }

        return $this->container->get('doctrine_mongodb');
    }

    /**
     * @param null $manager
     * @return mixed
     */
    protected function getDoctrineMongoManager($manager = null)
    {
        return $this->getDoctrineMongo()->getManager($manager);
    }

    /**
     * @param $object
     * @param null $manager
     * @return mixed
     */
    protected function getMongoRepository($object, $manager = null)
    {
        return $this->getDoctrineMongoManager($manager)->getRepository(is_object($object) ? get_class($object) : $object);
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
     * @param string $object
     * @param int $id
     * @param null $manager
     * @return mixed
     */
    protected function getDoctrineReference($object, $id, $manager = null)
    {
        return $this->getDoctrineManager($manager)->getReference($object, $id);
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
     * @return mixed
     */
    protected function getAsyncDispatcher()
    {
        if (!$this->has('bbit_async_dispatcher.dispatcher')) {
            $this->error('The bbit_async_dispatcher.dispatcher is not registered in your application.');
        }

        return $this->get('bbit_async_dispatcher.dispatcher');
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
     * @param $template
     */
    protected function setView($view)
    {
        $this->view = $view;
    }

    /**
     * @return View
     */
    protected function getView()
    {
        return $this->view;
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
    protected function CreateView($template = null, array $data = array(), Form $form = null, $statusCode = 200, array $headers = array())
    {
        if (!empty($data)) {
            $this->data = $data;
        }

        if (null !== $form) {
            $this->addForm($form);
        }
        
        if (null !== $template) {
            $this->template = $template;
        }

        $this->view = parent::view($this->data, $statusCode, $headers);
        
        if ("" != $this->template) {
            $this->view->setTemplate($this->template);
        }

        if ("" != $this->format) {
            $this->view->setFormat($this->format);
        }

        return $this->view;
    }

    /**
     * @param null $data
     * @param null $statusCode
     * @param array $headers
     */
    protected function CreateJsonView(array $data = array(), $statusCode = 200, array $headers = array())
    {
        if (!empty($data)) {
            $this->data = $data;
        }

        return new JsonResponse($this->data, $statusCode, $headers);
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
        dump($object);
        if ($exit) {
            die();
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
            $this->error('No null delete');
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
     * @param $eventName
     * @param Event $event
     * @return mixed
     */
    protected function dispatchAsync($eventName, Event $event = null)
    {
        return $this->getAsyncDispatcher()->addAsyncEvent($eventName, $event);
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
    protected function getWebBundlesDir()
    {
        return $this->getRootDir() . '/../web/bundles';
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
        return $this->memcache()->set($key, $data, $time);
    }

    /**
     * @param $to
     * @param $from
     * @param $to_name
     * @param $from_name
     * @param $subject
     * @return \Swift_Message
     */
    protected function message($to, $from, $to_name = "", $from_name = "", $subject = "email subject")
    {
        return \Swift_Message::newInstance()
            ->setTo($to, $to_name)
            ->setFrom($from, $from_name)
            ->setSubject($subject);
    }

    /**
     * @param $filename
     * @param $content_type
     * @param $body
     * @return \Swift_Attachment
     */
    protected function attachment($filename, $content_type, $body)
    {
        return \Swift_Attachment::newInstance()
            ->setFilename($filename)
            ->setContentType($content_type)
            ->setBody($body);
    }

    public function getExpresion()
    {
        if (null === $this->expression) {
            $this->expression = new ExpressionLanguage();
        }

        return $this->expression;
    }

    public function evaluate($expression, $values = array())
    {
        try {
            $result = $this->getExpresion()->evaluate($expression, $values);
        } catch (\Exception $e) {

            return array("result" => "KO");
        }

        return array(
            "result" => "OK",
            "value" => $result
        );
    }

    public function validateExpresion($expression)
    {
        try {
            $this->getExpresion()->parse($expression, array_keys($values));
            $this->getExpresion()->evaluate($expression, $values);
        } catch (\Exception $e) {

            return;
        }

        return true;
    }

        public function canonicalize($path)
    {
        return Path::canonicalize($path);
    }

}
