<?php

declare(strict_types=1);


namespace PBDKN\FussballBundle\Controller\ContentElement\FE;

use Contao\Config;
use Contao\ContentModel;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\String\HtmlDecoder;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\Environment;
use Contao\CoreBundle\ServiceAnnotation\ContentElement;
use Contao\Template;
use Contao\FrontendTemplate;
use Contao\Input;
use Contao\PageModel;
use Contao\Pagination;
use Contao\StringUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DoctrineDBALDriverException;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use PBDKN\FussballBundle\Util\CgiUtil;
use FOS\HttpCacheBundle\Http\SymfonyResponseTagger;
use Twig\Environment as TwigEnvironment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use PBDKN\FussballBundle\Controller\ContentElement\AbstractFussballController;
use PBDKN\FussballBundle\Controller\ContentElement\DependencyAggregate;

/**
 * Class MannschaftController
 *
 * @ContentElement(FeSpieleController::TYPE, category="fussball-FE")
 */
class FeSpieleController extends AbstractFussballController
{
    public const TYPE = 'AnzeigeSpiele';
    protected ContaoFramework $framework;
    protected Connection $connection;
    protected ?SymfonyResponseTagger $responseTagger;
    protected ?string $viewMode = null;
    protected ?ContentModel $model;
    protected ?PageModel $pageModel;
    protected TwigEnvironment $twig;

    // Adapters
    protected Adapter $config;
    protected Adapter $environment;
    protected Adapter $input;
    protected Adapter $stringUtil;
    
    private $Spiele = array();

    public function __construct(
      DependencyAggregate $dependencyAggregate, 
      ContaoFramework $framework, 
      TwigEnvironment $twig, 
      HtmlDecoder $htmlDecoder, 
      ?SymfonyResponseTagger $responseTagger)    
    {
        \System::log('PBD Spielecontroller ', __METHOD__, TL_GENERAL);

        parent::__construct($dependencyAggregate);  // standard Klassen plus akt. Wettbewerb lesen
                                                    // AbstractFussballController übernimmt sie in die akt Klasse
//        \System::log('PBD Spielecontroller nach dependencyAggregate', __METHOD__, TL_GENERAL);
        $this->framework = $framework;
        $this->twig = $twig;
        $this->htmlDecoder = $htmlDecoder;
        $this->responseTagger = $responseTagger;         //  FriendsOfSymfony/FOSHttpCacheBundle https://foshttpcachebundle.readthedocs.io/en/latest/ 

        // Adapters
        $this->config = $this->framework->getAdapter(Config::class);
        $this->environment = $this->framework->getAdapter(Environment::class);
        $this->input = $this->framework->getAdapter(Input::class);
        $this->stringUtil = $this->framework->getAdapter(StringUtil::class);

    }
    public function __invoke(Request $request, ContentModel $model, string $section, array $classes = null, PageModel $pageModel = null): Response
    {
        // Do not parse the content element in the backend
        if ($this->scopeMatcher->isBackendRequest($request)) {
            return new Response(
                $this->twig->render('@Fussball/Backend/backend_element_view.html.twig',  
                ['Wettbewerb'=>$this->aktWettbewerb['aktWettbewerb']])
            );
        }

        $this->model = $model;
        $this->pageModel = $pageModel;

        // Set the item from the auto_item parameter and remove auto_item from unused route parameters
        if (isset($_GET['auto_item']) && '' !== $_GET['auto_item']) {
            $this->input->setGet('auto_item', $_GET['auto_item']);
        }
        return parent::__invoke($request, $this->model, $section, $classes);
    }
    
    function replace16Bit($str) {
      // ersetze 16-bit Values
      $search  = array("\xC3\xA4", "\xC3\xB6", "\xC3\xBC", "\xC3\x84", "\xC3\x96","\xC3\x9f");
      $replace = array('ä', 'ö', 'ü', 'Ä', 'Ö','Ü','ß');
      $str= str_replace($search, $replace, $str);     
      return $str;     
    }
    /**
     * Generate the content element
     */
    protected function getResponse(Template $template, ContentModel $model, Request $request): ?Response
    {
        //$template->text = $model->text;
        $c=$this->cgiUtil;
        $html="";
        $Wettbewerb=$this->aktWettbewerb['aktWettbewerb'];
        // alle aktuellen Spiele einlesen        
        $sql  = "SELECT";
        $sql .= " hy_spiele.ID as 'ID',";
        $sql .= " hy_spiele.Nr as 'Nr',";
        $sql .= " hy_spiele.Gruppe as 'Gruppe',";
        $sql .= " hy_spiele.M1 as 'M1Ind',";
        $sql .= " mannschaft1.Nation as 'M1',";
        $sql .= " mannschaft1.Name as 'M1Name',";
        $sql .= " flagge1.Image as 'Flagge1',";
        $sql .= " hy_spiele.M2 as 'M2Ind',";
        $sql .= " mannschaft2.Nation as 'M2',";
        $sql .= " mannschaft2.Name as 'M2Name',";
        $sql .= " flagge2.Image as 'Flagge2',";
        $sql .= " hy_spiele.Ort as 'OrtInd',";
        $sql .= " hy_orte.Ort as 'Ort',";
        $sql .= " DATE_FORMAT(hy_spiele.Datum,'%d.%c') as 'Datum',";
        $sql .= " DATE_FORMAT(hy_spiele.Uhrzeit,'%H:%S') as 'Uhrzeit',";
        $sql .= " hy_spiele.T1 as 'T1',";
        $sql .= " hy_spiele.T2 as 'T2'";
        $sql .= " FROM hy_spiele";
        $sql .= " LEFT JOIN hy_mannschaft AS mannschaft1 ON hy_spiele.M1 = mannschaft1.ID";
        $sql .= " LEFT JOIN hy_nation AS flagge1 ON flagge1.ID = mannschaft1.flgindex";
        $sql .= " LEFT JOIN hy_mannschaft AS mannschaft2 ON hy_spiele.M2 = mannschaft2.ID";
        $sql .= " LEFT JOIN hy_nation AS flagge2 ON flagge2.ID = mannschaft2.flgindex";
        $sql .= " LEFT JOIN hy_orte ON hy_spiele.Ort = hy_orte.ID";
        $sql .= " WHERE hy_spiele.Wettbewerb  ='".$this->aktWettbewerb['aktWettbewerb']."'";
        $sql .= " ORDER BY hy_spiele.Datum ASC , hy_spiele.Uhrzeit ASC ;";
       

        $stmt = $this->connection->executeQuery($sql);
        $num_rows = $stmt->rowCount();    
        while (($row = $stmt->fetchAssociative()) !== false) {
          $this->Spiele[]=$row;
        }
        $rowcnt=1; 


        $html.="<div class='tabellenueberschrift'>Spielplan der ".$Wettbewerb." vom ".$this->fussballUtil->getDatum($this->aktWettbewerb,'start').' bis '.$this->fussballUtil->getDatum($this->aktWettbewerb,'ende')."&nbsp;<input class='druck' type='button' onclick='print()' value='Drucken'></div>\n";
        $html.=$c->table(array("class"=>"tablecss sortierbar","rules"=>"all","border"=>"1"));
        $html.=$c->thead();
	      $html.=$c->tr();
            //$html.=$c->th(array("class"=>"spielnrcss"),"Nr");
            $html.=$c->th(array("class"=>"datumcss"),"Datum");
            $html.=$c->th(array("class"=>"gruppecss sortierbar"),"Gruppe");
            $html.=$c->th(array("class"=>"heimcss sortierbar"),"Heim");
            $html.=$c->th(array("class"=>"scorecss sortierbar"),"Uhrzeit<br>Ergebnis");
            $html.=$c->th(array("class"=>"auswaertscss sortierbar"),"Ausw&auml;rts");
            $html.=$c->th(array("class"=>"ortcss sortierbar"),"Ort");
          $html.=$c->end_tr();
        $html.=$c->end_thead();
        $html.=$c->tbody();        
        foreach ($this->Spiele as $k=>$row) {
	      $html.=$c->tr();
            //$html.=$c->td(array("class"=>"spielnrcss"),(string)$row['Nr']);
            $html.=$c->td(array("class"=>"datumcss","data-label"=>"Datum"),$row['Datum']);
            $html.=$c->td(array("class"=>"gruppecss","data-label"=>"Gruppe"),$row['Gruppe']);
	        $str="<img src='".$this->fussballUtil->getImagePath($row['Flagge1']). "' >&nbsp;" . $row['M1Name'] ;
            $html.=$c->td(array("class"=>"heimcss","data-label"=>"Heim"),$str);
	        if ($row['T1'] == -1) {         // noch kein Ergebnis
              $html.=$c->td(array("class"=>"scorecss","data-label"=>"Uhrzeit"),$row['Uhrzeit']);
            } else {
              $html.=$c->td(array("class"=>"scorecss","data-label"=>"Ergebnis"),$row['T1'] . ":" . $row['T2']);
            }
	        $str="<img src='".$this->fussballUtil->getImagePath($row['Flagge2']). "' >&nbsp;" . $row['M2Name'] ;
            $html.=$c->td(array("class"=>"auswaertscss","data-label"=>"Ausw"),$str);
            $html.=$c->td(array("class"=>"ortcss","data-label"=>"Ort"),$row['Ort']);
          $html.=$c->end_tr();
          $rowcnt++;

        }
        $html.=$c->end_tbody();
        $html.=$c->end_table();
      //$html=replace16Bit($html);
      $response=new Response($html,Response::HTTP_OK,['content-type' => 'text/html']);
      return $response;
    }
}
