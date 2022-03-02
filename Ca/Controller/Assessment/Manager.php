<?php 
namespace Ca\Controller\Assessment;

use App\Controller\AdminManagerIface;
use Dom\Template;
use Tk\Request;

/**
 * TODO: Add Route to routes.php:
 *      $routes->add('ca-assessment-manager', Route::create('/staff/ca/assessmentManager.html', 'Ca\Controller\Assessment\Manager::doDefault'));
 *
 * @author Mick Mifsud
 * @created 2019-10-31
 * @link http://tropotek.com.au/
 * @license Copyright 2019 Tropotek
 */
class Manager extends AdminManagerIface
{

    /**
     * Manager constructor.
     */
    public function __construct()
    {
        $this->setPageTitle('Assessment Manager');
    }

    /**
     * @param Request $request
     * @throws \Exception
     */
    public function doDefault(Request $request)
    {
        $this->setTable(\Ca\Table\Assessment::create());
        $this->getTable()->setEditUrl(\Bs\Uri::createHomeUrl('/ca/assessmentEdit.html'));
        $this->getTable()->init();

        $filter = array('courseId' => $this->getCourseId());
        $this->getTable()->setList($this->getTable()->findList($filter));
    }

    /**
     * Add actions here
     */
    public function initActionPanel()
    {
        if ($this->getAuthUser()->isCoordinator()) {
            $this->getActionPanel()->append(\Tk\Ui\Link::createBtn('New Assessment',
                $this->getTable()->getEditUrl()->set('courseId', $this->getCourseId()), 'fa fa-book fa-add-action'));

            $this->getActionPanel()->append(\Tk\Ui\Link::createBtn('Domains',
                \Uni\Uri::createHomeUrl('/ca/domainManager.html')->set('courseId', $this->getCourseId()), 'fa fa-black-tie'));

            $this->getActionPanel()->append(\Tk\Ui\Link::createBtn('Competencies',
                \Uni\Uri::createHomeUrl('/ca/competencyManager.html')->set('courseId', $this->getCourseId()), 'fa fa-leaf'));
        }
        if ($this->getAuthUser()->isAdmin()) {
            $this->getActionPanel()->append(\Tk\Ui\Link::createBtn('Scales',
                \Uni\Uri::createHomeUrl('/ca/scaleManager.html')->set('courseId', $this->getCourseId()), 'fa fa-balance-scale'));
        }
    }

    /**
     * @return \Dom\Template
     */
    public function show()
    {
        $this->initActionPanel();
        $template = parent::show();

        $template->appendTemplate('panel', $this->getTable()->show());



        return $template;
    }

    /**
     * @return Template
     */
    public function __makeTemplate()
    {
        $xhtml = <<<HTML
<div class="tk-panel" data-panel-title="Assessments" data-panel-icon="fa fa-book" var="panel"></div>
HTML;
        return \Dom\Loader::load($xhtml);
    }
    
}