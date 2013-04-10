<?php

namespace Ace\UserBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Templating\EngineInterface;
use Symfony\Component\Security\Core\SecurityContext;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use FOS\UserBundle\Model\UserManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Ace\UserBundle\Form\Type\OptionsFormType;
use Ace\UserBundle\Entity\User;
use Symfony\Component\Security\Core\Encoder\EncoderFactory;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormError;
use Ace\UserBundle\Validator\Constraints\PasswordConstraint;
use Doctrine\ORM\EntityManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use MCAPI;

/**
 * Controller managing the user profile
 */
class OptionsController extends Controller
{
	protected $templating;
	protected $sc;
	protected $container;
	protected $request;
	protected $userManager;
	protected $encoderFactory;
	protected $entityManager;
	protected $listapi;
	protected $listid;
	
    public function optionsEditAction()
    {
		// Get currently logged in user
        $currentUser = $this->sc->getToken()->getUser();
        
        if (!is_object($currentUser) || !$currentUser instanceof User) {
            throw new AccessDeniedException('Sorry, this user does not have access to this section.');
        }
        
        // Get user's avatar
		$image = $this->get('ace_utilities.handler')->get_gravatar($currentUser->getEmail(), 120);
				
        $form = $this->createForm(new OptionsFormType());
        
		$form->get('username')	->setData($currentUser->getUsername());
		$form->get('firstname')	->setData($currentUser->getFirstname());
		$form->get('lastname')	->setData($currentUser->getLastname());
		$form->get('email')		->setData($currentUser->getEmail());
		$form->get('twitter')	->setData($currentUser->getTwitter());
		 
		if ('POST' === $this->request->getMethod()) {
			
		    $form->bindRequest($this->request);
		    
		    // PRE-CHECKS
						
			// Check if user entered his own password
			$currentPassword = $form->get('currentPassword')->getData();
			$authenticated = false;
			if(strlen($currentPassword) != 0){
				$authenticated = $this->isCurrentPass($currentPassword);
				if(!$authenticated)
					$form->get('currentPassword')->addError(new FormError("Sorry, wrong password!"));
			}
			
			// Check if email is changed and if it's available
			$email = $form->get('email')->getData();
			$emailChange = false;
			if($this->get('ace_user.usercontroller')->emailExistsAction($email) == "true"){
				if($email != $currentUser->getEmail())
					$form->get('email')->addError(new FormError("This Email address is already in use by another member"));
			}
			else{
				$emailChange = true;
				if(!$authenticated)
					$form->get('email')->addError(new FormError("Please provide your Current Password to change your Email"));
			}
			
			// Check if user wants to change his password and if it's valid
			$plainPassword = $form->get('plainPassword')->get('new')->getData();
			$passChange = false;
			if(strlen($plainPassword) != 0){
				if($authenticated){
					$passwordConstraint = new PasswordConstraint();
					$error = $this->get('validator')->validateValue($plainPassword,	$passwordConstraint);
					if(count($error) == 0)
						$passChange = true;
					else
						$form->get('plainPassword')->addError(new FormError($error[0]->getMessage()));
				}
				else
					$plainPasswordError = "Please provide your Current Password along with your New one.";
			}
		
			if ($form->isValid())
			{
				
				$this->em->persist($currentUser);
				
				// 	UPDATE USER
								 
				// update user's non-sensitive data only if modified
				// to avoid unnecessary db queries
				$updateNonSensitive = false;
				$firstname = $form->get('firstname')->getData();
				if($firstname !== $currentUser->getFirstname()){
					$currentUser->setFirstname($firstname);
					$updateNonSensitive = true;
				}
				
				$lastname = $form->get('lastname')->getData();
				if($lastname !== $currentUser->getLastname()){
					$currentUser->setLastname($lastname);
					$updateNonSensitive = true;
				}
				
				$twitter = $form->get('twitter')->getData();
				if($twitter !== $currentUser->getTwitter()){
					$currentUser->setTwitter($twitter);
					$updateNonSensitive = true;
				}
				
				$merge_vars = array();
				$api = new MCAPI($this->listapi);
				if($updateNonSensitive){
					$merge_vars = array("FNAME"=>$firstname, "LNAME"=>$lastname);
					$api->listUpdateMember($this->listid, $currentUser->getEmail(), $merge_vars, false);
				}
				
				$message = '<span style="color:green; font-weight:bold"><i class="icon-ok-sign icon-large"></i> SUCCESS:</span> Profile Updated!';
				
				$updateEmail = false;
				$updatePass = false;
				if($emailChange || $passChange){
					if($authenticated){
						if($emailChange){
							$merge_vars["EMAIL"] = $email;
							$api->listUpdateMember($this->listid, $currentUser->getEmail(), $merge_vars, false); //update newsletter info BEFORE updating user
							$currentUser->setEmail($email);
							$updateEmail = true;
						}
						if($passChange){
							$currentUser->setPlainPassword($form->get('plainPassword')->get('new')->getData());
							$this->um->updatePassword($currentUser);
							$updatePass = true;
						}
					}
					else
						$message = '<span style="color:orange; font-weight:bold"><i class="icon-warning-sign icon-large"></i> WARNING:</span> Your Profile was updated <strong>except the fields that contain errors</strong>, please fix the errors and try again to update them too.';				
				}	
				if($updateNonSensitive || $updateEmail || $updatePass){
					// update user db
					$this->em->flush();
					$this->um->reloadUser($currentUser);
				}
			}
			else
				$message = '<span style="color:red; font-weight:bold"><i class="icon-remove-sign icon-large"></i> ERROR:</span> Your Profile was <strong>NOT updated</strong>, please fix the errors and try again.';
				
			//get errors from fields and store them in an assosiative array
			$response = $this->getErrorMessages($form);
			$response["message"] = $message;
			
			// transfer error from plainPassword key to plainPasswordError variable
			//if(isset($response["plainPassword"])){
			//	$plainPasswordError = $response["plainPassword_"];
			//	unset($response["plainPassword"]);
			//}
			// add custom error message to response
			if(isset($plainPasswordError))
				$response["plainPassword"] = $plainPasswordError;
			
			return new Response(json_encode($response));
        }
        else
			return new Response($this->templating->render('AceUserBundle:Default:options.html.twig', array('form' => $form->createView(), 'image' => $image, "user" => $currentUser)));
		

    }
    
    private function isCurrentPass($currentPassword){
		
			return $this->comparePassword($currentPassword);
	}
    
    public function isCurrentPasswordAction(){
		
		if("POST" === $this->request->getMethod()){
			$currentPassword = $this->request->get('currentPassword');
			$return = $this->comparePassword($currentPassword);
			$response = array('valid' => $return);
							
			return new Response(json_encode($response), 200, array('Content-Type'=>'application/json'));
		}
	}
	
	private function comparePassword($currentPassword){	
		
		$currentUser = $this->sc->getToken()->getUser();
		$encoder = $this->ef->getEncoder($currentUser);
		$encodedPass = $encoder->encodePassword($currentPassword, $currentUser->getSalt());
			
		if($encodedPass === $currentUser->getPassword())
			return true;
		
		return false;
	}
    
    public function isEmailAvailableAction(){
		
		if("POST" === $this->request->getMethod()){
			$currentUser = $this->sc->getToken()->getUser();
			$email = $this->request->get('email');
			
			// TODO: find out why $this->get('ace_user.usercontroller')->emailExistsAction($email) doesn't work
			$exists = $this->em->getRepository('AceUserBundle:User')->findOneByEmail($email);
			if($exists){
					if($email !== $currentUser->getEmail())
						$return = 'inUse'; //in use by another member
					else
						$return = 'own'; //already stored
			}
			else
				$return = 'available'; //success! New available email
				
			$response = array('valid' => $return);
							
			return new Response(json_encode($response), 200, array('Content-Type'=>'application/json'));
		}
	}
    
    private function getErrorMessages(Form $form) {
    
		$errors = array();
		foreach ($form->getErrors() as $key => $error) {
			$template = $error->getMessageTemplate();
			$parameters = $error->getMessageParameters();

			foreach($parameters as $var => $value){
				$template = str_replace($var, $value, $template);
			}

			$errors[$key] = $template;
		}
		if ($form->hasChildren()) {
			foreach ($form->getChildren() as $child) {
				if (!$child->isValid()) {
					$errors[$child->getName()] = $this->getErrorMessages($child);
				}
			}
		}

		return $errors;
	}
 
	public function __construct(EngineInterface $templating,
								SecurityContext $securityContext,
								ContainerInterface $container,
								Request $request,
								UserManagerInterface $userManager,
								EncoderFactory $encoderFactory,
								EntityManager $entityManager,
								$listapi,
								$listid)
	{
		$this->templating = $templating;
		$this->sc = $securityContext;
		$this->container = $container;
		$this->request=$request;
		$this->um=$userManager;
		$this->ef=$encoderFactory;
		$this->em=$entityManager;
		$this->listapi=$listapi;
		$this->listid=$listid;
	}

}
