<?php
namespace Module;

use CI_Module;

class Post extends CI_Module
{
    protected $module_uri = 'https://www.pentagonal.org';

    protected $module_author = 'awan';

    protected $module_author_uri = 'https://www.pentagonal.org';

    protected $module_description = 'Module for displaying posts & page';

    protected $module_name = 'Official Post Module';

    protected $module_version = 'v1.0';

    /**
     * Initial on before route
     * just like on your module initiate
     */
    public function initial()
    {
    }
}
