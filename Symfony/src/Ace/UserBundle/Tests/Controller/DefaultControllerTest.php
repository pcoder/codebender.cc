<?php

namespace Ace\UserBundle\Tests\Controller;
use Ace\UserBundle\Controller\DefaultController;
use Doctrine\ORM\Query;
use Symfony\Component\HttpFoundation\Response;

class DefaultControllerTest extends \PHPUnit_Framework_TestCase
{
	public function testExistsAction_Exists()
	{
		$this->initArguments($templating, $security, $em, $container);

		$controller = $this->getMock("Ace\UserBundle\Controller\DefaultController", array("getUserAction"), array($templating, $security, $em, $container));

		$controller->expects($this->once())->method('getUserAction')->with($this->equalTo("iamfake"))->will($this->returnValue(new Response('{"success":true,"id":1,"email":"a@fake.email","username":"iamfake","firstname":"fake","lastname":"basterd","twitter":"atwitteraccount","karma":150,"points":150,"referrals":5,"referrer_username":null,"referral_code":null,"walkthrough_status":0}')));

		$response = $controller->existsAction("iamfake");
		$this->assertEquals($response->getContent(), 'true');
	}

	public function testExistsAction_NoUser()
	{
		$this->initArguments($templating, $security, $em, $container);

		$controller = $this->getMock("Ace\UserBundle\Controller\DefaultController",array("getUserAction"), array($templating, $security, $em, $container));

		$controller->expects($this->once())->method('getUserAction')->with($this->equalTo("idontexist"))->will($this->returnValue(new Response('{"success":false}')));

		$response = $controller->existsAction("idontexist");
		$this->assertEquals($response->getContent(), 'false');
	}

	public function testEmailExistsAction_EmailExists()
	{
		$user = $this->getMockBuilder('Ace\UserBundle\Entity\User')
			->disableOriginalConstructor()
			->getMock();

		$repo = $this->getMockBuilder('Doctrine\ORM\EntityRepository')
			->disableOriginalConstructor()
			->setMethods(array("findOneByEmail"))
			->getMock();

		$repo->expects($this->once())->method('findOneByEmail')->with($this->equalTo("iamfake"))->will($this->returnValue($user));

		$controller = $this->setUpController($templating, $security, $em, $container);

		$em->expects($this->once())->method('getRepository')->with($this->equalTo('AceUserBundle:User'))->will($this->returnValue($repo));

		$response = $controller->emailExistsAction("iamfake");
		$this->assertEquals($response->getContent(), 'true');
	}

	public function testEmailExistsAction_NoEmail()
	{
		$repo = $this->getMockBuilder('Doctrine\ORM\EntityRepository')
			->disableOriginalConstructor()
			->setMethods(array("findOneByEmail"))
			->getMock();

		$repo->expects($this->once())->method('findOneByEmail')->with($this->equalTo("idontexist"))->will($this->returnValue(null));

		$controller = $this->setUpController($templating, $security, $em, $container);

		$em->expects($this->once())->method('getRepository')->with($this->equalTo('AceUserBundle:User'))->will($this->returnValue($repo));

		$response = $controller->emailExistsAction("idontexist");
		$this->assertEquals($response->getContent(), 'false');
	}

	public function testGetUserAction_UserExists()
	{
		$user = $this->getMockBuilder('Ace\UserBundle\Entity\User')
			->disableOriginalConstructor()
			->getMock();

		$user->expects($this->once())->method('getId')->will($this->returnValue(1));
		$user->expects($this->once())->method('getEmail')->will($this->returnValue("a@fake.email"));
		$user->expects($this->once())->method('getUsername')->will($this->returnValue("iamfake"));
		$user->expects($this->once())->method('getFirstname')->will($this->returnValue("fake"));
		$user->expects($this->once())->method('getLastname')->will($this->returnValue("basterd"));
		$user->expects($this->once())->method('getTwitter')->will($this->returnValue("atwitteraccount"));
		$user->expects($this->once())->method('getKarma')->will($this->returnValue(150));
		$user->expects($this->once())->method('getPoints')->will($this->returnValue(150));
		$user->expects($this->once())->method('getReferrals')->will($this->returnValue(5));
		$user->expects($this->once())->method('getReferrerUsername')->will($this->returnValue(null));
		$user->expects($this->once())->method('getReferralCode')->will($this->returnValue(null));
		$user->expects($this->once())->method('getWalkthroughStatus')->will($this->returnValue(0));

		$repo = $this->getMockBuilder('Doctrine\ORM\EntityRepository')
			->disableOriginalConstructor()
			->setMethods(array("findOneByUsername"))
			->getMock();

		$repo->expects($this->once())->method('findOneByUsername')->with($this->equalTo("iamfake"))->will($this->returnValue($user));

		$controller = $this->setUpController($templating, $security, $em, $container);

		$em->expects($this->once())->method('getRepository')->with($this->equalTo('AceUserBundle:User'))->will($this->returnValue($repo));

		$response = $controller->getUserAction("iamfake");
		$this->assertEquals($response->getContent(), '{"success":true,"id":1,"email":"a@fake.email","username":"iamfake","firstname":"fake","lastname":"basterd","twitter":"atwitteraccount","karma":150,"points":150,"referrals":5,"referrer_username":null,"referral_code":null,"walkthrough_status":0}');
	}

	public function testGetUserAction_NoUser()
	{
		$repo = $this->getMockBuilder('Doctrine\ORM\EntityRepository')
			->disableOriginalConstructor()
			->setMethods(array("findOneByUsername"))
			->getMock();

		$repo->expects($this->once())->method('findOneByUsername')->with($this->equalTo("idontexist"))->will($this->returnValue(null));

		$controller = $this->setUpController($templating, $security, $em, $container);

		$em->expects($this->once())->method('getRepository')->with($this->equalTo('AceUserBundle:User'))->will($this->returnValue($repo));

		$response = $controller->getUserAction("idontexist");
		$this->assertEquals($response->getContent(), '{"success":false}');
	}

	public function testGetCurrentUserAction_userLoggedIn()
	{
		$user = $this->getMockBuilder('Ace\UserBundle\Entity\User')
			->disableOriginalConstructor()
			->getMock();

		$user->expects($this->once())->method('getUsername')->will($this->returnValue("iamfake"));

		$token = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')
			->disableOriginalConstructor()
			->getMock();

		$this->initArguments($templating, $security, $em, $container);

		$controller = $this->getMock("Ace\UserBundle\Controller\DefaultController", array("getUserAction"), array($templating, $security, $em, $container));
		$controller->expects($this->once())->method('getUserAction')->with($this->equalTo("iamfake"))->will($this->returnValue(new Response('{"success":true,"id":1,"email":"a@fake.email","username":"iamfake","firstname":"fake","lastname":"basterd","twitter":"atwitteraccount","karma":150,"points":150,"referrals":5,"referrer_username":null,"referral_code":null,"walkthrough_status":0}')));

		$token->expects($this->once())->method('getUser')->will($this->returnValue($user));
		$security->expects($this->any())->method('getToken')->will($this->returnValue($token));

		$response = $controller->getCurrentUserAction();
		$this->assertEquals($response->getContent(), '{"success":true,"id":1,"email":"a@fake.email","username":"iamfake","firstname":"fake","lastname":"basterd","twitter":"atwitteraccount","karma":150,"points":150,"referrals":5,"referrer_username":null,"referral_code":null,"walkthrough_status":0}');
	}

	/**
	 * @expectedException Symfony\Component\HttpKernel\Exception\NotFoundHttpException
	 */
	public function testGetCurrentUserAction_userNotFound()
	{
		$user = $this->getMockBuilder('Ace\UserBundle\Entity\User')
			->disableOriginalConstructor()
			->getMock();

		$user->expects($this->once())->method('getUsername')->will($this->returnValue("idontexist"));

		$token = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')
			->disableOriginalConstructor()
			->getMock();

		$token->expects($this->once())->method('getUser')->will($this->returnValue($user));

		$this->initArguments($templating, $security, $em, $container);
		$controller = $this->getMock("Ace\UserBundle\Controller\DefaultController", array("getUserAction"), array($templating, $security, $em, $container));
		$controller->expects($this->once())->method('getUserAction')->with($this->equalTo("idontexist"))->will($this->returnValue(new Response('{"success":false}')));

		$security->expects($this->any())->method('getToken')->will($this->returnValue($token));

		$controller->getCurrentUserAction();
	}

	public function testGetCurrentUserAction_userAnonymous()
	{

		$token = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')
			->disableOriginalConstructor()
			->getMock();

		$token->expects($this->once())->method('getUser')->will($this->returnValue("anon."));

		$controller = $this->setUpController($templating, $security, $em, $container);

		$security->expects($this->any())->method('getToken')->will($this->returnValue($token));

		$response = $controller->getCurrentUserAction();
		$this->assertEquals($response->getContent(), '{"success":false}');
	}

	private function initArguments(&$templating, &$security, &$em, &$container)
	{
		$em = $this->getMockBuilder('Doctrine\ORM\EntityManager')
			->disableOriginalConstructor()
			->getMock();

		$templating = $this->getMockBuilder('Symfony\Bundle\TwigBundle\TwigEngine')
			->setMethods(null)
			->disableOriginalConstructor()
			->getMock();

		$security = $this->getMockBuilder('Symfony\Component\Security\Core\SecurityContext')
			->disableOriginalConstructor()
			->getMock();

		$container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
	}

	private function setUpController(&$templating, &$security, &$em, &$container)
		{
			$this->initArguments($templating, $security, $em, $container);
			$controller = new DefaultController($templating, $security, $em, $container);
			return $controller;
		}
}
