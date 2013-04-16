<?php

namespace Ace\ProjectBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Ace\ProjectBundle\Entity\Project as Project;
use Doctrine\ORM\EntityManager;
use Ace\ProjectBundle\Controller\MongoFilesController;

class DefaultController extends Controller
{
    protected $em;
	protected $fc;
    protected $sl;


	public function createprojectAction($user_id, $project_name, $code)
	{
		$retval;
		$response = $this->createAction($user_id, $project_name, "")->getContent();
		$response=json_decode($response, true);
		if($response["success"])
		{
			$response2 = $this->createFileAction($response["id"], $project_name.".ino", $code)->getContent();
			$response2=json_decode($response2, true);
			if($response2["success"])
			{
				$retval = array("success" => true, "id" => $response["id"]);
			}
			else
				$retval = $response2;
		}
		else
			$retval = $response;

		return new Response(json_encode($retval));
	}

	public function listAction($owner)
	{
		$projects = $this->em->getRepository('AceProjectBundle:Project')->findByOwner($owner);
		$list = array();
		foreach($projects as $project)
		{
			$list[] = array("id"=> $project->getId(), "name"=>$project->getName(), "description"=>$project->getDescription(), "is_public"=>$project->getIsPublic());
		}
		return new Response(json_encode($list));
	}

	public function createAction($owner, $name, $description)
	{
		$validName = json_decode($this->nameIsValid($name), true);
		if(!$validName["success"])
			return new Response(json_encode($validName));

		$project = new Project();
		$user = $this->em->getRepository('AceUserBundle:User')->find($owner);
		$project->setOwner($user);
	    $project->setName($name);
	    $project->setDescription($description);
	    $project->setIsPublic(true);
        $project->setType($this->sl);
        $response = json_decode($this->fc->createAction(), true);

		if($response["success"])
		{
			$id = $response["id"];
			$project->setProjectfilesId($id);

		    $em = $this->em;
		    $em->persist($project);
		    $em->flush();

		    return new Response(json_encode(array("success" => true, "id" => $project->getId())));
		}
		else
			return new Response(json_encode(array("success" => false, "owner_id" => $user->getId(), "name" => $name)));
	}
	
	public function deleteAction($id)
	{
		$project = $this->getProjectById($id);
        $deletion = json_decode($this->fc->deleteAction($project->getProjectfilesId()), true);

		if($deletion["success"] == true)
		{
		    $em = $this->em;
			$em->remove($project);
			$em->flush();
			return new Response(json_encode(array("success" => true)));
		}
		else
		{
			return new Response(json_encode(array("success" => false, "id" => $project->getProjectfilesId())));
		}
		
	}

	public function cloneAction($owner, $id)
	{
        $project = $this->getProjectById($id);
        $new_name=$project->getName();
        $nameExists = json_decode($this->nameExists($owner,$new_name), true);
        while($nameExists["success"])
        {
            $new_name = $new_name." copy";
            $nameExists = json_decode($this->nameExists($owner,$new_name), true);
        }
        $response = json_decode($this->createAction($owner,$new_name,$project->getDescription())->getContent(),true);

		if($response["success"] == true)
		{
            $list = json_decode($this->listFilesAction($project->getId())->getContent(), true);
            foreach($list["list"] as $file)
            {
                if(pathinfo($file["filename"], PATHINFO_EXTENSION)== "ino")
                {
                    $this->createFileAction($response["id"],$new_name.".ino",$file["code"]);
                }
                else
                {
                $this->createFileAction($response["id"],$file["filename"],$file["code"]);
                }
            }
		    return new Response(json_encode(array("success" => true, "id" => $response["id"])));
		}
		else
		{
			return new Response(json_encode(array("success" => false, "id" => $id)));
		}

	}

    public function renameAction($id, $new_name)
    {
        $validName = json_decode($this->nameIsValid($new_name), true);
        if(!$validName["success"])
            return new Response(json_encode($validName));

        $output = array("success" => true);

        $project = $this->getProjectById($id);
        $name = $project->getName();

        $filename = $name.".ino";

        $response1 = json_decode($this->fc->renameFileAction($project->getProjectfilesId(), $filename, $new_name.".ino.bkp"), true);
        if($response1["success"])
        {
            $response2 = json_decode($this->fc->renameFileAction($project->getProjectfilesId(), $new_name.".ino.bkp", $new_name.".ino"), true);
            if($response2["success"])
            {
                $project->setName($new_name);
                $em = $this->em;
                $em->persist($project);
                $em->flush();
            }
            else
            {
                $output = $response2;
                $output["error"] = "backup file ".$new_name.".ino.bkp"." could not be renamed. ".$output["error"];
            }
        }
        else
        {
            $output = $response1;
            $output["error"] = "old file ".$filename." could not be renamed. ".$output["error"];
        }

        return new Response(json_encode($output));


    }

	public function getNameAction($id)
	{
		$project = $this->getProjectById($id);
		$name = $project->getName();
		return new Response(json_encode(array("success" => true, "response" => $name)));
	}

	public function getParentAction($id)
	{
		$project = $this->getProjectById($id);
		$parent = $project->getParent();
		if($parent != NULL)
		{
			$exists = json_decode($this->checkExistsAction($id)->getContent(), true);
			if ($exists["success"])
			{
				$parent = $this->getProjectById($parent);
				$response = array("id" => $parent->getId(), "owner" => $project->getOwner()->getUsername(), "name" => $project->getName());
				return new Response(json_encode(array("success" => true, "response" => $response)));
			}
		}

		return new Response(json_encode(array("success" => false)));
	}

	public function getOwnerAction($id)
	{
		$project = $this->getProjectById($id);
		$user = $project->getOwner();
		$response = array("id" => $user->getId(), "username" => $user->getUsername(), "firstname" => $user->getFirstname(), "lastname" => $user->getLastname());
		return new Response(json_encode(array("success" => true, "response" => $response)));
	}

	public function getDescriptionAction($id)
	{
		$project = $this->getProjectById($id);
		$response = $project->getDescription();
		return new Response(json_encode(array("success" => true, "response" => $response)));
	}

	public function setDescriptionAction($id, $description)
	{
		$project = $this->getProjectById($id);
		$project->setDescription($description);
	    $em = $this->em;
	    $em->persist($project);
	    $em->flush();
		return new Response(json_encode(array("success" => true)));
	}
	
	public function listFilesAction($id)
	{
		$project = $this->getProjectById($id);

        $list = $this->fc->listFilesAction($project->getProjectfilesId());
		return new Response($list);
	}

	public function createFileAction($id, $filename, $code)
	{
		$project = $this->getProjectById($id);

        $canCreate = json_decode($this->canCreateFile($project->getId(), $filename), true);
        if($canCreate["success"])
        {
            $create = $this->fc->createFileAction($project->getProjectfilesId(), $filename, $code);
            $retval = $create;
        }
        else
        {
            $retval = json_encode($canCreate);
        }
        return new Response($retval);
	}
	
	public function getFileAction($id, $filename)
	{
		$project = $this->getProjectById($id);

        $get = $this->fc->getFileAction($project->getProjectfilesId(), $filename);
		return new Response($get);
		
	}
	
	public function setFileAction($id, $filename, $code)
	{
		$project = $this->getProjectById($id);

        $set = $this->fc->setFileAction($project->getProjectfilesId(), $filename, $code);
		return new Response($set);
		
	}
		
	public function deleteFileAction($id, $filename)
	{
		$project = $this->getProjectById($id);

        $delete = $this->fc->deleteFileAction($project->getProjectfilesId(), $filename);
		return new Response($delete);
	}

	public function renameFileAction($id, $filename, $new_filename)
	{
		$project = $this->getProjectById($id);

        $delete = $this->fc->renameFileAction($project->getProjectfilesId(), $filename, $new_filename);
		return new Response($delete);
	}

	public function searchAction($token)
	{
		$results_name = json_decode($this->searchNameAction($token)->getContent(), true);
		$results_desc = json_decode($this->searchDescriptionAction($token)->getContent(), true);
		$results = $results_name + $results_desc;
		return new Response(json_encode($results));
	}

	public function searchNameAction($token)
	{
		$em = $this->em;
		$repository = $this->em->getRepository('AceProjectBundle:Project');
		$qb = $em->createQueryBuilder();
		$projects = $repository->createQueryBuilder('p')->where('p.name LIKE :token')->setParameter('token', "%".$token."%")->getQuery()->getResult();
		$result = array();
		foreach($projects as $project)
		{
			$owner = json_decode($this->getOwnerAction($project->getId())->getContent(), true);
			$owner = $owner["response"];
			$proj = array("name" => $project->getName(), "description" => $project->getDescription(), "owner" => $owner);
			$result[] = array($project->getId() => $proj);
		}
		return new Response(json_encode($result));
	}

	public function searchDescriptionAction($token)
	{
		$em = $this->em;
		$repository = $this->em->getRepository('AceProjectBundle:Project');
		$qb = $em->createQueryBuilder();
		$projects = $repository->createQueryBuilder('p')->where('p.description LIKE :token')->setParameter('token', "%".$token."%")->getQuery()->getResult();
		$result = array();
		foreach($projects as $project)
		{
			$owner = json_decode($this->getOwnerAction($project->getId())->getContent(), true);
			$owner = $owner["response"];
			$proj = array("name" => $project->getName(), "description" => $project->getDescription(), "owner" => $owner);
			$result[] = array($project->getId() => $proj);
		}
		return new Response(json_encode($result));
	}

	public function checkExistsAction($id)
	{
		$em = $this->em;
		$project = $this->em->getRepository('AceProjectBundle:Project')->find($id);
	    if (!$project)
			return new Response(json_encode(array("success" => false)));
		return new Response(json_encode(array("success" => true)));
	}

	public function getProjectById($id)
	{
		$em = $this->em;
		$project = $this->em->getRepository('AceProjectBundle:Project')->find($id);
	    if (!$project)
	        throw $this->createNotFoundException('No project found with id '.$id);			
			// return new Response(json_encode(array(false, "Could not find project with id: ".$id)));
		
		return $project;
	}


    private function inoExists($id)
    {
        $list = json_decode($this->listFilesAction($id)->getContent(), true);
        if($list["success"])
        {
            foreach($list["list"] as $file)
            {
               if(pathinfo($file["filename"], PATHINFO_EXTENSION)=="ino")
                   return json_encode(array("success" => true));
            }
        }
        return json_encode(array("success" => false, "error" => ".ino file does not exist."));
    }

    private function canCreateFile($id, $filename)
    {
        if(pathinfo($filename, PATHINFO_EXTENSION)== "ino")
        {
            $inoExists = json_decode($this->inoExists($id), true);
            if($inoExists["success"])
                return json_encode(array("success" => false, "id" => $id, "filename" => $filename, "error" => "Cannot create second .ino file in the same project"));
        }

        return json_encode(array("success" => true));
    }


	private function nameIsValid($name)
	{
		$project_name = str_replace(".", "", trim(basename(stripslashes($name)), ".\x00..\x20"));
		if($project_name == $name)
			return json_encode(array("success" => true));
		else
			return json_encode(array("success" => false, "error" => "Invalid Name. Please enter a new one."));
	}

    private function nameExists($owner, $name)
    {
        $userProjects = json_decode($this->listAction($owner)->getContent(),true);

        foreach($userProjects as $p)
        {
            if ($p["name"] == $name)
            {
                return json_encode(array("success" => true));
            }
        }
        return json_encode(array("success" => false));
    }
	public function __construct(EntityManager $entityManager, MongoFilesController $mongoFilesController, DiskFilesController $diskFilesController, $storageLayer)
	{
	    $this->em = $entityManager;
        $this->sl = $storageLayer;
        if($this->sl == "disk")
        {
            $this->fc = $diskFilesController;
        }
        else if ($this->sl == "mongo")
        {
            $this->fc = $mongoFilesController;
        }
        else
        {
            throw new \Exception('Invalid Storage Layer');
        }
	}
}
