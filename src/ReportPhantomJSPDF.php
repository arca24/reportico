<?php
/*
Reportico - PHP Reporting Tool
Copyright (C) 2010-2014 Peter Deed

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

 * File:        Report_pdf.php
 *
 * Base class for all report output formats.
 * Defines base functionality for handling report
 * page headers, footers, group headers, group trailers
 * data lines
 *
 * @link http://www.reportico.org/
 * @copyright 2010-2014 Peter Deed
 * @author Peter Deed <info@reportico.org>
 * @package Reportico
 * @license - http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 * @version $Id: swoutput.php,v 1.33 2014/05/17 15:12:31 peter Exp $
 */
namespace Reportico\Engine;

use JonnyW\PhantomJs\Client;

class ReportPhantomJSPDF extends Report
{
    private $client = false;

    public function __construct() {
        $this->column_spacing = 0;
    }

    public function start($engine)
    {
        if ( !$engine->pdf_phantomjs_temp_path )
            $engine->pdf_phantomjs_temp_path = __DIR__."/../tmp/";

        // Instantiate PhantomJS
        $this->client = Client::getInstance();
        $this->client->isLazy();
        $this->client->getProcedureCompiler()->disableCache();
        if ( $engine->pdf_phantomjs_path )
            $this->client->getEngine()->setPath($engine->pdf_phantomjs_path);

        // Build URL - dont include scheme and port if already provided
        $url = "{$engine->reportico_ajax_script_url}?execute_mode=EXECUTE&target_format=HTML2PDF&reportico_session_name=" . (ReporticoSession())::reporticoSessionName();
        if ( !preg_match("/:\/\//", $url) ) 
        $url = "${_SERVER["REQUEST_SCHEME"]}://${_SERVER["HTTP_HOST"]}:${_SERVER["SERVER_PORT"]}{$engine->reportico_ajax_script_url}?execute_mode=EXECUTE&target_format=HTML2PDF&reportico_session_name=" . (ReporticoSession())::reporticoSessionName();

        // Add in any extra forwarded URL parameters
        if ($engine->forward_url_get_parameters) 
            $url .= "&".$engine->forward_url_get_parameters;

        // Generate Request Call
        $request = $this->client->getMessageFactory()->createPdfRequest($url, 'GET', 4000);

        // Add any CSRF tokens for when Reportico is called inside a framework
        // And retain any cookies too
        if ( $engine->csrfToken ) {

            // Its Laravel 
            $oldHeaders = getallheaders();

            if ( isset($oldHeaders["Cookie"]) )
                $newHeaders["Cookie"]= $oldHeaders["Cookie"];
            $newHeaders["X-CSRF-TOKEN"]= $engine->csrfToken;

            $request->setHeaders($newHeaders);
        }

        // Generate temporary name for pdf file to generate on disk. Since phantomjs must write to a file with pdf extension use tempnam, to create a file
        // without PDF extensiona and then delete this and use the name with etension for phantom generation
        $outputfile = tempnam($engine->pdf_phantomjs_temp_path, "pdf");
        
        unlink($outputfile);
        $outputfile .= ".pdf";
        $request->setOutputFile($outputfile);

        // Set other PhantomJS parameters
        $request->setFormat(strtoupper($engine->getAttribute("PageSize")));
        $request->setOrientation(strtolower($engine->getAttribute("PageOrientation")));
        $request->setMargin(0);
        $request->setDelay(2);

        $headertext .= '';

        /*
        foreach ($engine->pageHeaders as $header) {

            //break;
            $styles = "";
            $text = $header->text;
            $attr = [];

            //if ( $header->getAttribute("ShowInPDF") != "yes" ) {
                //continue;
            //}

            if ( $text == "{NOMORE}" )
                break;

            $text = preg_replace("/{PAGE}/i", "%pageNum%", $text); 
            $text = preg_replace("/{PAGETOTAL}/i", "%pageTotal%", $text); 

            ReportHtml::extractStylesAndTextFromStringStandalone($text, $styles, $attr);
            $text = Report::reporticoStringToPhpStandalone($text, $engine);
            $text = Assignment::reporticoMetaSqlCriteria($engine, $text, false, true);
            $just = strtolower($header->getAttribute("justify"));

            $styles = "position:absolute;$styles";

            if ( $just == "center" || $just == "centre") $styles .= "width: 98%; text-align: center;";
            if ( $just == "right" ) $styles .= "width: 98%; text-align: right";

            $img = "";
            if ($styles) {
                $matches = array();
                if (preg_match("/background: url\('(.*)'\).*;/", $styles, $matches)) {
                    $styles = preg_replace("/background: url\('(.*)'\).*;/", "", $styles);
                    if (count($matches) > 1) {
                        $img = "<img src='" . $matches[1] . "'/>";
                    }
                }
                $headertext .= "<DIV class=\"reportico-page-header\" style=\"$styles\">";
            } else {
                $headertext .= "<DIV class=\"reportico-page-header\" >";
            }

            $headertext .= "$img$text";
            $headertext .= "</DIV>";
        }
        
        $footertext .= '';
        foreach ($engine->pageFooters as $footer) {

            $styles = "";
            $text = $footer->text;
            $attr = [];

            if ( $footer->getAttribute("ShowInPDF") != "yes" ) {
                continue;
            }

            if ( $text == "{NOMORE}" )
                break;

            $text = preg_replace("/{PAGE}/i", "%pageNum%", $text); 
            $text = preg_replace("/{PAGETOTAL}/i", "%pageTotal%", $text); 

            ReportHtml::extractStylesAndTextFromStringStandalone($text, $styles, $attr);
            $text = Report::reporticoStringToPhpStandalone($text, $engine);

            //$text = Assignment::reporticoMetaSqlCriteria($engine, $text);
            $just = strtolower($footer->getAttribute("justify"));

            $styles = "position:absolute;$styles";

            if ( $just == "center" || $just == "centre") $styles .= "width: 98%; text-align: center;";
            if ( $just == "right" ) $styles .= "width: 98%; text-align: right";

            $img = "";
            if ($styles) {
                $matches = array();
                if (preg_match("/background: url\('(.*)'\).*;/", $styles, $matches)) {
                    $styles = preg_replace("/background: url\('(.*)'\).*;/", "", $styles);
                    if (count($matches) > 1) {
                        $img = "<img src='" . $matches[1] . "'/>";
                    }
                }
                $footertext .= "<DIV class=\"reportico-page-footer\" style=\"$styles\">";
            } else {
                $footertext .= "<DIV class=\"reportico-page-footer\" >";
            }

            $footertext .= "$img$text";
            $footertext .= "</DIV>";
        }
        $request->setRepeatingHeader('<div style="position:relative; zoom: 0.68;">'.$headertext.'</div>', $engine->getAttribute("TopMargin"));
        $request->setRepeatingFooter('<div class="reportico-page-footer-block" style="margin-top: 5px;border-top: solid 1px">'.$footertext.'</div>', $engine->getAttribute("BottomMargin"));
        */

        // Get Response
        $response = $this->client->getMessageFactory()->createResponse();

        // Since we are going to spawn web call to fetch HTML version of report for conversion to PDF, 
        // we must close current sessions so they can be subsequently opened within the web call
        (ReporticoSession())::closeReporticoSession();

        // Send the request
        $this->client->send($request, $response);
        if( $response->getStatus() !== 200) {
            header("HTTP/1.0 {$response->getStatus()}", true);
            echo "Failed to produce PDF file error {$response->getStatus()} - <BR>Content:<b><BR>{$response->getContent()}</b> - <BR>";
            die;
        }

        //header('content-type: application/pdf');
        //echo file_get_contents('/tmp/document.pdf');
        //die;

        $this->reporttitle = $engine->deriveAttribute("ReportTitle", "Set Report Title");
        if (isset($engine->user_parameters["custom_title"])) {
            $reporttitle = $engine->user_parameters["title"];
            //$engine->setAttribute("ReportTitle", $reporttitle);
        }

        $attachfile = "reportico.pdf";
        $reporttitle = ReporticoLang::translate($engine->deriveAttribute("ReportTitle", "Unknown"));
        if ($reporttitle) {
            $attachfile = preg_replace("/ /", "_", $reporttitle . ".pdf");
        }

        // INLINE output is just returned to browser window it is invoked from
        // with hope that browser uses plugin
        if ($engine->pdf_delivery_mode == "INLINE") {
            header("Content-Type: application/pdf");
            echo file_get_contents($outputfile);
            unlink($outputfile);
            die;
        }

        // DOWNLOAD_SAME_WINDOW output is ajaxed back to current browser window and then downloaded
        else if ($engine->pdf_delivery_mode == "DOWNLOAD_SAME_WINDOW" /*&& $engine->reportico_ajax_called*/) {
            header('Content-Disposition: attachment;filename=' . $attachfile);
            header("Content-Type: application/pdf");
            $buf = base64_encode(file_get_contents($outputfile));
            unlink($outputfile);
            //$buf = file_get_contents($outputfile);
            $len = strlen($buf);
            echo $buf;
            die;
        }
        // DOWNLOAD_NEW_WINDOW new browser window is opened to download file
        else {
            header('Content-Disposition: attachment;filename=' . $attachfile);
            header("Content-Type: application/pdf");
            echo file_get_contents($outputfile);
            unlink($outputfile);
            die;
        }
        die;
    }

}
