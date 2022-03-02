<?php
namespace Ca\Util\Pdf;

use Ca\Db\Scale;
use Dom\Renderer\Renderer;
use Dom\Template;
use Tk\ConfigTrait;

/**
 * @author Michael Mifsud <info@tropotek.com>
 * @link http://www.tropotek.com/
 * @license Copyright 2018 Michael Mifsud
 *
 * @note This file uses the mpdf lib
 * @link https://mpdf.github.io/
 */
class Entry extends \Dom\Renderer\Renderer implements \Dom\Renderer\DisplayInterface
{
    use ConfigTrait;

    /**
     * @var \Ca\Db\Entry
     */
    protected $entry = null;

    /**
     * @var \Mpdf\Mpdf
     */
    protected $mpdf = null;

    /**
     * @var string
     */
    protected $watermark = '';

    /**
     * @var bool
     */
    private $rendered = false;


    /**
     * HtmlInvoice constructor.
     * @param \Ca\Db\Entry $entry
     * @param string $watermark
     * @throws \Exception
     */
    public function __construct($entry, $watermark = '')
    {
        $this->entry = $entry;
        $this->watermark = $watermark;

        $this->initPdf();
    }

    /**
     * @param \Ca\Db\Entry $entry
     * @param string $watermark
     * @return Entry
     * @throws \Exception
     */
    public static function create($entry, $watermark = '')
    {
        $obj = new self($entry, $watermark);
        return $obj;
    }

    /**
     * @return \Ca\Db\Entry
     */
    public function getEntry()
    {
        return $this->entry;
    }

    /**
     * @throws \Exception
     */
    protected function initPdf()
    {
        $html = $this->show()->toString();

        $tpl = \Tk\CurlyTemplate::create($html);
        $parsedHtml = $tpl->parse(array());
        $this->mpdf = new \Mpdf\Mpdf(array(
			'format' => 'A4-P',
            'orientation' => 'P',
            'margin_left' => 5,
            'margin_right' => 5,
            'margin_top' => 5,
            'margin_bottom' => 15,
            'margin_header' => 0,
            'margin_footer' => 3,
            'use_kwt' => 1,
            'tempDir' => $this->getConfig()->getTempPath()
        ));
        $mpdf = $this->mpdf;
        //$mpdf->setBasePath($url);
        $mpdf->use_kwt = true;
        //$mpdf->shrink_tables_to_fit = 0;
        //$mpdf->useSubstitutions = true;       // optional - just as an example
        //$mpdf->CSSselectMedia='mpdf';         // assuming you used this in the document header
        //$mpdf->SetProtection(array('print'));

        $mpdf->SetTitle($this->getEntry()->getTitle());
        if ($this->getEntry()->getAssessor())
            $mpdf->SetAuthor($this->getEntry()->getAssessor()->getName());

        if ($this->watermark) {
            $mpdf->SetWatermarkText($this->watermark);
            $mpdf->showWatermarkText = true;
            $mpdf->watermark_font = 'DejaVuSansCondensed';
            $mpdf->watermarkTextAlpha = 0.1;
        }
        $mpdf->SetDisplayMode('fullpage');


        $mpdf->SetHTMLFooter('
<table width="100%" style="vertical-align: bottom; font-family: serif; font-size: 8pt; 
      color: #000000; font-weight: bold; font-style: italic;border-top: 1px solid #000;" cellpadding="10">
  <tr>
    <td width="33%">{DATE j-m-Y}</td>
    <td width="33%" align="center">{PAGENO}/{nbpg}</td>
    <td width="33%" style="text-align: right;">'.$this->getEntry()->getAssessment()->getName().'</td>
  </tr>
</table>');

        $mpdf->WriteHTML($parsedHtml);
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getFilename()
    {
        return $this->getEntry()->getAssessment()->getName() . '-' . $this->getEntry()->getId() . '.pdf';
    }

    /**
     * Output the pdf to the browser
     *
     * @throws \Exception
     */
    public function download()
    {
        $this->mpdf->Output($this->getFilename(), \Mpdf\Output\Destination::DOWNLOAD);
    }

    /**
     * Output the pdf to the browser
     *
     * @throws \Exception
     */
    public function output()
    {
        $filename = $this->getEntry()->getAssessment()->getName() . '-' . $this->getEntry()->getId() . '.pdf';
        $this->mpdf->Output($this->getFilename(), \Mpdf\Output\Destination::INLINE);
    }

    /**
     * Retun the PDF as a string to attache to an email message
     *
     * @param string $filename
     * @return string
     * @throws \Exception
     */
    public function getPdfAttachment($filename = '')
    {
        if (!$filename)
            $filename = $this->getFilename();
        return $this->mpdf->Output($filename, \Mpdf\Output\Destination::STRING_RETURN);
    }

    /**
     * Execute the renderer.
     * Return an object that your framework can interpret and display.
     *
     * @return null|Template|Renderer
     * @throws \Exception
     */
    public function show()
    {
        $template = $this->getTemplate();
        if ($this->rendered) return $template;
        $this->rendered = true;

        $css = <<<CSS
.doc {
/*  padding-top: 5px; */
}
h1 {
  text-align: center;
}
.head table { 
  margin: 0 auto;
  width: 80%;
  background: #EFEFEF;
}
.head table td, .head table th {
  padding: 5px;
}

.content {
  padding: 10px 20px;
}
.items {
  margin: 0px auto;
  width: 100%;
}
.items .t-id {
  width: 5%;
  text-align: center;
}
.items .t-data {
  width: 15%;
  text-align: center;
}
.items td {
  padding: 10px 5px;
}
.items tr:nth-child(odd) td {
 /*  #ffffcc, #e8fdff, #f8f8f8   */
  background-color: #f8f8f8;
}
.category h3 {
  margin-top: 20px;
}
.tag {
  font-size: 0.7em;
}
pre {
  display: block;
  font-size: 0.8em;
}
CSS;
        $template->appendCss($css);

        $template->insertText('heading', $this->getEntry()->getAssessment()->getName() . ' View');

        $this->addTableRow('head-row', 'Title', $this->getEntry()->getTitle());
        $this->addTableRow('head-row', 'Status', ucwords($this->getEntry()->getStatus()));
        if ($this->getEntry()->getAssessor())
            $this->addTableRow('head-row', 'Assessor', $this->getEntry()->getAssessor()->getName());
        $this->addTableRow('head-row', 'Days Absent', (int)$this->getEntry()->getAbsent().'');
        $this->addTableRow('head-row', 'Comments', $this->getEntry()->getNotes());

        $items = \Ca\Db\ItemMap::create()->findFiltered(array('assessmentId' => $this->getEntry()->getAssessment()->getId()),
            \Tk\Db\Tool::create('order_by'));

        /** @var \Ca\Db\Item $item */
        $domainId = 0;
        /** @var null|\Dom\Repeat $catRepeat */
        $catRepeat = null;
        foreach ($items as $i => $item) {
            if ($item->getDomainId() != $domainId) {
                if ($catRepeat) $catRepeat->appendRepeat();
                $catRepeat = $template->getRepeat('category');
                if ($item->getDomain())
                    $catRepeat->insertText('category-name', $item->getDomain()->getName());
                $domainId = $item->getDomainId();
            }
            $value = 0;
            $itemVal = \Ca\Db\EntryMap::create()->findValue($this->entry->getId(), $item->getId());
            if ($itemVal)
                $value = $itemVal->value;

            $repeat = $catRepeat->getRepeat('item');

            $cHtml = '';
            if ($item->getName())
                $cHtml .= $item->getName() . "<br/>\n";
            $compList = $item->getCompetencyList();
            if ($compList->count()) {
                foreach ($compList as $competency) {
                    $cHtml .= $competency->getName() . "<br/>\n";
                }
            }

            if ($item->getScale()->getType() == Scale::TYPE_CHOICE) {
                $scaleList = $item->getScale()->getOptions()->toArray('name');
                $tot = $item->getScale()->getOptions()->count()-1;
                $repeat->insertHtml('data', $value . '/' . $tot);       // TODO: see if this is correct.
                $repeat->insertHtml('tag', $scaleList[$value]);
            } else if ($item->getScale()->getType() === Scale::TYPE_TEXT) {
                $cHtml .= sprintf("<br/><p><pre>%s</pre></p>\n", $value);
            } else {
                $repeat->insertHtml('data', $value);
            }

            $repeat->insertHtml('label', $cHtml);
            $repeat->insertHtml('id', $i+1);

            $repeat->appendRepeat();
        }
        if ($catRepeat) $catRepeat->appendRepeat();

        return $template;
    }

    /**
     * @param string $var
     * @param string $label
     * @param string $data
     * @param null|\Dom\Template $template
     */
    public function addTableRow($var, $label, $data, $template = null)
    {
        if (!$template) $template = $this->getTemplate();
        $repeat = $template->getRepeat($var);
        $repeat->insertHtml('label', $label);
        $repeat->insertHtml('data', $data);
        $repeat->appendRepeat();
    }

    /**
     * DomTemplate magic method
     *
     * @return \Dom\Template
     */
    public function __makeTemplate()
    {
        $xhtml = <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8" />
  <title></title>
</head>
<body class="" style="" var="body">

<div class="doc">
  <h1 var="heading"></h1>
  
  <div class="head" var="head">
    <table class="items">
      <tr var="head-row" repeat="head-row">
        <th var="label"></th>
        <td var="data"></td>
      </tr>
    </table>
  </div>
  <br/>
  <div class="content" var="content">
    <div class="category" var="category" repeat="category">
    
      <h3 var="category-name"></h3>
      <table class="items" cellspacing="0">
        <tbody>
          <tr var="item" repeat="item">
            <td class="t-id" var="id"></td>
            <td class="t-label" var="label"></td>
            <td class="t-data"><div var="data"></div><div class="tag" var="tag"></div></td>
          </tr>
        </tbody>
      </table>
      
    </div>
    
  </div>
  
  <div class="foot" var="foot"></div>
</div>

</body>
</html>
HTML;

        return \Dom\Loader::load($xhtml);
    }

}