<?php

namespace Ace\GenericBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;


class DefaultControllerFunctionalTest extends WebTestCase
{
	public function testIndexAction() // Test homepage and redirection bug
	{
		$client = static::createClient();
		$crawler = $client->request('GET', '/');

		$this->assertFalse($client->getResponse()->isRedirect());

		$this->assertEquals(1, $crawler->filter('html:contains("code fast. code easy. codebender")')->count());
		$this->assertEquals(1, $crawler->filter('html:contains("online development & collaboration ")')->count());
	}


	public function testUserAction() // Test user page
	{
		$client = static::createClient();

		$crawler = $client->request('GET', '/user/tester');

		$this->assertEquals(1, $crawler->filter('html:contains("tester")')->count());
		$this->assertEquals(1, $crawler->filter('html:contains("myfirstname")')->count());
		$this->assertEquals(1, $crawler->filter('html:contains("mylastname")')->count());

		$matcher = array('id'   => 'user_projects');
		$this->assertTag($matcher, $client->getResponse()->getContent());
	}


	public function testUserActionLinksToSketchView_SketchViewWorks() // Test project page
	{
//		$client = static::createClient();
//
//		$crawler = $client->request('GET', '/user/tester');
//
//		$client->followRedirects();
//
//		$link = $crawler->filter('#user_projects')->children()->eq(1)->children()->children()->children()->link();
//		$crawler = $client->click($link);
//
//		$matcher = array('id'   => 'code-container');
//		$this->assertTag($matcher, $client->getResponse()->getContent());
		//TODO: check for real project (needs internet connection)
		$this->markTestIncomplete('Not functional tested yet.');
	}

	public function testLibraries()
	{
		$client = static::createClient();

		$crawler = $client->request('GET', '/libraries');
		$this->assertEquals(1, $crawler->filter('html:contains("codebender libraries")')->count());
		$this->assertEquals(1, $crawler->filter('html:contains("Request Library")')->count());

		//TODO: check for existing libraries (needs internet connection)
		$this->markTestIncomplete('Not functional tested yet.');
	}

	public function testFunctionalTested()
	{
		$this->markTestIncomplete('Not functional tested yet.');
	}
}