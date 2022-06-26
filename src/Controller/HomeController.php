<?php 

namespace App\Controller ;

use App\Entity\User;
use App\Entity\Commande;
use App\Entity\Vehicule;
use App\Form\CommandeType;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;


class HomeController extends AbstractController{

    #[Route("/" , name:"home_index")]
    public function index () :Response{
       
        return $this->render("front/index.html.twig");
    }

    #[Route("/search" , name:"home_search")]
    public function search (Request $request , EntityManagerInterface $em ) :Response{
        $dtDebut = new \DateTime($request->request->get("dt_debut"));
        $dtFin = new \DateTime($request->request->get("dt_fin"));
        $request->getSession()->set('dateDebut', $dtDebut);
        $request->getSession()->set('dateFin', $dtFin);
        $listevehiculeLoue = $em->getRepository(Commande::class)->listeVehiculeLoue($dtDebut ,$dtFin );
        $listevehiculeDisponible = $em->getRepository(Vehicule::class)->findByVehiculeDisponibles( $listevehiculeLoue );
        
       // dump($dtDebut , $dtFin, $listevehiculeLoue , $listevehiculeDisponible);

        return $this->render("front/resultats.html.twig" , [
            "vehicules" => $listevehiculeDisponible
        ]);
    }

    #[Route("/louer" , name:"home_rent")]
    public function rent(AuthenticationUtils $authenticationUtils, Request $request){

        $request->getSession()->set('vehicule', $request->get('id'));

        $user = new User();
        $formInscription = $this->createForm(RegistrationFormType::class , $user);

        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render("front/registration.html.twig" , [
            "formInscription" => $formInscription->createView(),
            'last_username' => $lastUsername, 
            'error' => $error
        ]);
    }

    #[Route("/commande" , name:"home_end")]
    public function commande(Request $request,EntityManagerInterface $em){

        $vehicule = $request->getSession()->get('vehicule');

        $vehicule = $em->getRepository(Vehicule::class)->find($vehicule);

        $commande = new Commande();
        $commande->setUser($this->getUser());
        $commande->setVehicule($vehicule);
        $commande->setDateHeureDepart( $request->getSession()->get('dateDebut'));
        $commande->setDateHeureFin($request->getSession()->get('dateFin'));

        $form = $this->createForm(CommandeType::class , $commande);

        $form->handleRequest($request);

        if($form->isSubmitted()) {

            $interval = $commande->getDateHeureDepart()->diff($commande->getDateHeureFin());
            $interval->format("%d");
            $nbJours = $interval->days ;
            $prix_journalier = $vehicule->getPrixJournalier();

            $commande->setPrixTotal(($nbJours * $prix_journalier));


            $em->persist($commande);

            $em->flush();

            return $this->redirectToRoute('/profil');
        }


        return $this->render("front/commande.html.twig" , [
            "form" => $form->createView()
        ] );
    }

    #[Route('/profil', name:"mon_compte")]
    public function profil(EntityManagerInterface $em){
        
       $commandes = $em->getRepository(Commande::class)->findBy(['user' => $this->getUser()]);


        return $this->render("membre/profil.html.twig",  ["commandes" => $commandes]); 
    }

}