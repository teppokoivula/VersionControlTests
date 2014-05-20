<?php

/**
 * PHPUnit tests for Version Control ProcessWire module
 * 
 * Intended to be run against a clean installation of ProcessWire with Version
 * Control available. Most of the tests depend on each other so they're grouped
 * together into one file and use depends annotation.
 *
 * DO NOT run these tests against production site, as they will add, edit and
 * remove pages when necessary, thus potentially seriously damaging your site!
 * 
 * @author Teppo Koivula, <teppo@flamingruby.com>
 * @copyright Copyright (c) 2014, Teppo Koivula
 * @license GNU/GPL v2, see LICENSE
 * 
 */
class VersionControlTest extends PHPUnit_Framework_TestCase {

    /**
     * Static properties shared by all tests
     *
     */
    protected static $data;
    protected static $module_name;

    /**
     * Executed once before tests
     *
     * Set test environment up by removing old data, bootstrapping ProcessWire
     * and making sure that module undergoing tests is uninstalled.
     *
     */
    public static function setUpBeforeClass() {

        // Messages and errors
        $messages = array();
        $errors = array();

        // Set module name
        self::$module_name = substr(__CLASS__, 0, strlen(__CLASS__)-4);

        // Create page field and add it to basic-page template
        $field_name = "page";
        $field = wire('fields')->get($field_name);
        if (!$field || !$field->id) {
            $field = new Field;
            $field->type = wire('modules')->get('FieldtypePage');
            $field->name = $field_name;
            $field->parent_id = 1; // home
            $field->inputfield = 'InputfieldAsmSelect';
            $field->save();
            $messages[] = substr($field->type, 9) . " field '{$field->name}' added";
        }
        $fieldgroup = wire('fieldgroups')->get('basic-page');
        if (!$fieldgroup->hasField($field)) {
            $fieldgroup->add($field);
            $fieldgroup->save();
            $messages[] = substr($field->type, 9) . " field '{$field->name}' added to fieldgroup '{$fieldgroup->name}'";
        }

        // Create checkbox field and add it to basic-page template
        $field_name = "checkbox";
        $field = wire('fields')->get($field_name);
        if (!$field || !$field->id) {
            $field = new Field;
            $field->type = wire('modules')->get('FieldtypeCheckbox');
            $field->name = $field_name;
            $field->save();
            $messages[] = substr($field->type, 9) . " field '{$field->name}' added";
        }
        $fieldgroup = wire('fieldgroups')->get('basic-page');
        if (!$fieldgroup->hasField($field)) {
            $fieldgroup->add($field);
            $fieldgroup->save();
            $messages[] = substr($field->type, 9) . " field '{$field->name}' added to fieldgroup '{$fieldgroup->name}'";
        }

        // Create repeater field and add it to basic-page template
        $field_name = "repeater";
        $field = wire('fields')->get($field_name);
        if (!$field || !$field->id) {
            $fieldgroup = new Fieldgroup;
            $fieldgroup->name = "repeater_" . $field_name;
            $fieldgroup->append(wire('fields')->get('title'));
            $fieldgroup->save();
            $template = new Template;
            $template->name = "repeater_" . $field_name;
            $template->fieldgroup = $fieldgroup;
            $template->flags = Template::flagSystem;	
            $template->noChildren = 1; 
            $template->noParents = 1;
            $template->noGlobal = 1; 
            $template->save();
            $field = new Field;
            $field->type = wire('modules')->get('FieldtypeRepeater');
            $field->name = $field_name;
            $field->parent_id = wire('pages')->get("name=for-field-{$field->id}")->id;
            $field->template_id = $template->id;
            $field->save();
            $messages[] = substr($field->type, 9) . " field '{$field->name}' added";
        }
        $fieldgroup = wire('fieldgroups')->get('basic-page');
        if (!$fieldgroup->hasField($field)) {
            $fieldgroup->add($field);
            $fieldgroup->save();
            $messages[] = substr($field->type, 9) . " field '{$field->name}' added to fieldgroup '{$fieldgroup->name}'";
        }

        // Create image field and add it to basic-page template
        $field_name = "image";
        $field = wire('fields')->get($field_name);
        if (!$field || !$field->id) {
            $field = new Field;
            $field->type = wire('modules')->get('FieldtypeImage');
            $field->name = $field_name;
            $field->save();
            $messages[] = substr($field->type, 9) . " field '{$field->name}' added";
        }
        $fieldgroup = wire('fieldgroups')->get('basic-page');
        if (!$fieldgroup->hasField($field)) {
            $fieldgroup->add($field);
            $fieldgroup->save();
            $messages[] = substr($field->type, 9) . " field '{$field->name}' added to fieldgroup '{$fieldgroup->name}'";
        }

        // Remove any pages created but not removed during previous tests
        foreach (wire('pages')->find("name^=a-test-page, include=all") as $page) {
            $page->delete();
            $messages[] = get_class($page) . " '{$page->url}' deleted";
        }

        // Uninstall module (if installed)
        if (wire('modules')->isInstalled(self::$module_name)) {
            if (wire('modules')->isUninstallable(self::$module_name)) {
                wire('modules')->uninstall(self::$module_name);
                $messages[] = "Module '" . self::$module_name . "' uninstalled";
            } else {
                $errors[] = "Module '" . self::$module_name . "' not uninstallable, please uninstall manually before any new tests";
            }
        }

        // Language tests require "reloadLanguages" method
        if (method_exists(wire('languages'), 'reloadLanguages')) {

            // Create dummy languages
            $languages_page = wire('pages')->get(wire('modules')->get('LanguageSupport')->languagesPageID);
            $language_names = array('java', 'perl');
            while ($language_name = array_shift($language_names)) {
                $language = wire('languages')->get($language_name);
                if (!$language->id) {
                    $language = wire('languages')->add($language_name);
                    wire('languages')->reloadLanguages();
                    $messages[] = get_class($language) . " '" . $language->name . "' added";
                }
            }
            
            // Install LanguageSupportFields (unless already installed)
            $module_name = 'LanguageSupportFields';
            if (!wire('modules')->isInstalled($module_name)) {
                $module = wire('modules')->getInstall($module_name);
                if (wire('modules')->isInstalled($module_name)) {
                    $messages[] = "Module '{$module_name}' installed";
                } else {
                    $errors[] = "Unable to install '{$module_name}'";
                }
            }
            
            // Create new multi-language textfield and add it to basic-page template
            $field = wire('fields')->get('text_language');
            if (!$field || !$field->id) {
                $field = new Field;
                $field->type = wire('modules')->get('FieldtypeTextLanguage');
                $field->name = 'text_language';
                $field->save();
                $messages[] = substr($field->type, 9) . " field '{$field->name}' added";
            }
            $fieldgroup = wire('fieldgroups')->get('basic-page');
            if (!$fieldgroup->hasField($field)) {
                $fieldgroup->add($field);
                $fieldgroup->save();
                $messages[] = substr($field->type, 9) . " field '{$field->name}' added to fieldgroup '{$fieldgroup->name}'";
            }

            // Create new language alternate field for checkbox created earlier
            $field = wire('fields')->get('checkbox_java');
            if (!$field || !$field->id) {
                $field = new Field;
                $field->type = wire('modules')->get('FieldtypeCheckbox');
                $field->name = 'checkbox_java';
                $field->save();
                $messages[] = substr($field->type, 9) . " field '{$field->name}' added";
            }
            $fieldgroup = wire('fieldgroups')->get('basic-page');
            if (!$fieldgroup->hasField($field)) {
                $fieldgroup->add($field);
                $fieldgroup->save();
                $messages[] = substr($field->type, 9) . " field '{$field->name}' added to fieldgroup '{$fieldgroup->name}'";
            }

        }
            
        // Messages and errors
        if ($messages) echo "* " . implode($messages, "\n* ") . "\n\n";
        if ($errors) die("* " . implode($errors, "\n* ") . "\n\n");
        
        // Setup static variables
        self::$data = array();

    }

    /**
     * Executed once after all tests are finished
     *
     * Cleanup; remove any pages created but not removed during tests (and
     * uninstall the module) in order to prepare this site for new tests.
     *
     */
    public static function tearDownAfterClass() {

        // Messages and errors
        $messages = array();
        $errors = array();

        // Remove any pages created but not removed during tests
        foreach (wire('pages')->find("title^='a test page', include=all") as $page) {
            $page->delete();
            $messages[] = get_class($page) . " '{$page->url}' deleted";
        }

        // Remove repeater field from templates (or fieldgroups) it's added to
        // and then remove the field itself
        $field = wire('fields')->get('repeater');
        if ($field->id) {
            $fieldgroups = $field->getFieldgroups();
            foreach ($fieldgroups as $fieldgroup) {
                $fieldgroup->remove($field);
                $fieldgroup->save();
                $messages[] = substr($field->type, 9) . " field '{$field->name}' removed from fieldgroup '{$fieldgroup->name}'";
            }
            foreach (wire('pages')->find("template=repeater_{$field->name}, include=all") as $page) {
                $page->delete();
                $messages[] = get_class($page) . " '{$page->url}' deleted";
            }
            wire('fields')->delete($field);
            $messages[] = substr($field->type, 9) . " field '{$field->name}' deleted";
        }

        // Remove "regular" fields from templates (or fieldgroups) they're added
        // to and then remove the fields themselves
        $fields = array('page', 'text_language', 'checkbox', 'checkbox_java', 'image');
        foreach ($fields as $field) {
            $field = wire('fields')->get($field);
            if ($field && $field->id) {
                $fieldgroups = $field->getFieldgroups();
                foreach ($fieldgroups as $fieldgroup) {
                    $fieldgroup->remove($field);
                    $fieldgroup->save();
                    $messages[] = substr($field->type, 9) . " field '{$field->name}' removed from fieldgroup '{$fieldgroup->name}'";
                }
                wire('fields')->delete($field);
                $messages[] = substr($field->type, 9) . " field '{$field->name}' deleted";
            }
        }

        // Uninstall module (if installed)
        if (wire('modules')->isInstalled(self::$module_name)) {
            if (wire('modules')->isUninstallable(self::$module_name)) {
                wire('modules')->uninstall(self::$module_name);
                $messages[] = "Module '" . self::$module_name . "' uninstalled";
            } else {
                $errors[] = "Module '" . self::$module_name . "' not uninstallable, please uninstall manually before any new tests";
            }
        }

        // Remove dummy languages
        if (method_exists(wire('languages'), 'reloadLanguages')) {
            $language_names = array('java', 'perl');
            while ($language_name = array_shift($language_names)) {
                $language = wire('languages')->get($language_name);
                if ($language->id) {
                    $language->delete();
                    $messages[] = get_class($language) . " '" . $language->name . "' deleted";
                }
            }
        }

        // Uninstall LanguageSupportFields
        if (wire('modules')->isInstalled('LanguageSupportFields')) {
            wire('modules')->uninstall('LanguageSupportFields');
            if (!wire('modules')->isInstalled('LanguageSupportFields')) {
                $messages[] = "Uninstalled language support for fields (LanguageSupportFields)";
            } else {
                $errors[] = "Unable to uninstall language support for fields (LanguageSupportFields)";
            }
        }

        // Messages and errors
        if ($messages) echo "\n\n* " . implode($messages, "\n* ");
        if ($errors) die("\n* " . implode($errors, "\n* ") . "\n");
        
    }

    /**
     * Executed after each test
     *
     * Almost all tests end with same assertions, so it makes sense to move
     * those here where they get executed automatically after each test.
     *
     */
    public function tearDown() {

        // Skip teardown for some tests
        $skip_teardown = array(
            "testModuleIsInstallable",
            "testModuleIsUninstallable",
            "testUninstallModule",
        );
        if (in_array($this->getName(), $skip_teardown)) return;

        // Start constructing database query
        $t1 = constant(self::$module_name . "::TABLE_REVISIONS");
        $t2 = constant(self::$module_name . "::TABLE_DATA");
        $t3 = constant(self::$module_name . "::TABLE_FILES");
        $t4 = constant(self::$module_name . "::TABLE_DATA_FILES");
        $columns = array(
            'pages_id',
            'fields_id',
            'users_id',
            'username',
            'property',
            'data',
            'filename',
            'mime_type',
            'size',
        );
        $joins = array(
            "JOIN {$t2} t2 ON t2.revisions_id = t1.id",
            "LEFT OUTER JOIN {$t4} t4 ON t4.data_id = t2.id",
            "LEFT OUTER JOIN {$t3} t3 ON t3.id = t4.files_id",
        );

        // Fetch content from version control database tables
        $sql = "SELECT " . implode(", ", $columns) . " FROM {$t1} t1 " . implode(" ", $joins);
        $result = wire('db')->query($sql);

        // Compare fetched rows to temporary array containing local data rows
        $data = self::$data;
        while ($row = $result->fetch_assoc()) {
            $data_row = count($data) ? array_combine($columns, array_pad(array_values(array_shift($data)), count($columns), null)) : null;
            $message = array();
            if (!$data_row) {
                $data_row = array();
                $message[] = "Local data row was NULL, using placeholder array";
            }
            foreach ($data_row as $key => $value) if (strpos($value, "{") === 0 && ($json = json_decode($value, true)) !== null) $data_row[$key] = $json;
            foreach ($row as $key => $value) if (strpos($value, "{") === 0 && ($json = json_decode($value, true)) !== null) $row[$key] = $json;
            $message[] = "Query: {$sql}";
            if ($row['filename']) {
                if (!function_exists("rscandir")) {
                    function rscandir($path, $files = array()) {
                        if (strpos(strrev($path), "/") !== 0) $path .= "/";
                        foreach (scandir($path) as $file) if (strpos($file, ".") !== 0) $files[$file] = is_dir($path.$file) ? rscandir($path.$file) : "file";
                        return count($files) ? $files : "empty directory";
                    }
                }
                $message[] = "Files in " . wire('modules')->VersionControl->path . ": " . var_export(rscandir(wire('modules')->VersionControl->path), true);
            }
            $this->assertEquals($data_row, $row, $message ? implode("\n", $message) . "\n" : null);
        }

        // There shouldn't be any data left in aforementioned temporary array
        if ($data) {
            $data = var_export($data, true);
            $this->fail("Local data has more rows than database:\n\n$data");
        }

        // Check for orphaned or missing rows in files table
        $error = "";
        $sql = "
        SELECT d.id data, f.id files
        FROM version_control__data_files df
        LEFT JOIN version_control__data d ON df.data_id = d.id
        LEFT JOIN version_control__files f ON df.files_id = f.id
        ";
        $result = wire('db')->query($sql);
        while ($row = $result->fetch_assoc()) {
            $row_export = var_export($row, true);
            if (is_null($row['data'])) $error .= "Extra row in files table: $row_export\n";
            if (is_null($row['files'])) $error .= "Missing row from files table: $row_export\n";
        }
        if ($error) $this->fail($error);

    }
    
    /**
     * Make sure that module is installable
     *
     * @return string module name
     */
    public function testModuleIsInstallable() {
        $this->assertTrue(wire('modules')->isInstallable(self::$module_name));
        return self::$module_name;
    }

    /**
     * Install module
     * 
     * @depends testModuleIsInstallable
     * @param string $module_name
     * @return string module name
     */
    public function testInstallModule($module_name) {
        
        // Install the module
        wire('modules')->install($module_name);
        $this->assertTrue(wire('modules')->isInstalled($module_name));
        
        // Configure the module
        $data = array(
            'enabled_templates' => array(
                29, // basic-page
            ),
            'enabled_fields' => array(
                1, // title
                76, // body
                wire('fields')->get('page')->id,
                wire('fields')->get('checkbox')->id,
                wire('fields')->get('images')->id,
                wire('fields')->get('image')->id,
            ),
        );
        if (method_exists(wire('languages'), "reloadLanguages")) {
            array_push(
                $data['enabled_fields'],
                wire('fields')->get('checkbox_java')->id,
                wire('fields')->get('text_language')->id
            );
        }
        $defaults = VersionControl::getDefaultData();
        $data = array_merge($defaults, $data);
        wire('modules')->saveModuleConfigData($module_name, $data);
        wire('modules')->triggerInit();

        return $module_name;

    }

    /**
     * Add new page
     *
     * Only field under version control that we're modifying here is 'title',
     * so we should get one new row in both version control database tables.
     *
     * @depends testInstallModule
     * @return Page
     */
    public function testAddPage() {
        $page = new Page;
        $page->parent = wire('pages')->get('/');
        $template = wire('templates')->get('basic-page');
        $page->template = wire('templates')->get('basic-page');
        $page->title = "a test page";
        $page->save();
        self::$data[] = array((string) $page->id, "1", "40", "guest", "data", "a test page");
        return $page;
    }

    /**
     * Make a change to previously added page
     *
     * Both 'title' and 'body' are fields tracked by version control module,
     * so we should get two new rows in both version control database tables.
     * 
     * @depends testAddPage
     * @param Page $page
     * @return Page
     */
    public function testEditPage(Page $page) {
        $page->title = "a test page 2";
        $page->body = "body text";
        $page->checkbox = 1;
        $page->save();
        self::$data[] = array((string) $page->id, "1", "40", "guest", "data", "a test page 2");
        self::$data[] = array((string) $page->id, "76", "40", "guest", "data", "body text");
        self::$data[] = array((string) $page->id, (string) wire('fields')->get('checkbox')->id, "40", "guest", "data", "1");
        return $page;
    }

    /**
     * Make a change to (and save) a single field
     * 
     * @depends testEditPage
     * @param Page $page
     * @return Page
     */
    public function testEditField(Page $page) {
        $page->checkbox = 0;
        $page->save('checkbox');
        self::$data[] = array((string) $page->id, (string) wire('fields')->get('checkbox')->id, "40", "guest", "data", "0");
        return $page; 
    }

    /**
     * Make changes to language fields
     *
     * Editing value of one multi-language field in two different languages,
     * both of which were created during setup. This should add two rows to
     * version control database tables.
     *
     * @todo module should be updated to avoid always storing empty value for
     *       default language. This feature depends on ProcessWire issue #373.
     * @depends testEditField
     * @param Page $page
     * @return Page
     */
    public function testEditMultiLanguageFields(Page $page) {
        if (!method_exists(wire('languages'), 'reloadLanguages')) {
            $this->markTestSkipped("wire('languages') doesn't have reloadLanguages method");
        }
        $java = wire('languages')->get('java');
        $perl = wire('languages')->get('perl');
        $page->text_language->setLanguageValue($java, 'since 1995');
        $page->text_language->setLanguageValue($perl, 'since 1987');
        $page->checkbox_java = 1;
        $page->save();
        self::$data[] = array((string) $page->id, (string) wire('fields')->get('text_language')->id, "40", "guest", "data" . $java, "since 1995");
        self::$data[] = array((string) $page->id, (string) wire('fields')->get('text_language')->id, "40", "guest", "data" . $perl, "since 1987");
        self::$data[] = array((string) $page->id, (string) wire('fields')->get('checkbox_java')->id, "40", "guest", "data", "1");
        return $page;
    }

    /**
     * Unpublish previously added page
     *
     * Since no fields under version control are affected, this shouldn't add
     * any rows to version control database tables.
     *
     * @depends testEditPage
     * @param Page $page
     * @return Page
     */
    public function testUnpublishPage(Page $page) {
        $page->addStatus(Page::statusUnpublished);
        $page->save();
        return $page;
    }

    /**
     * Publish previously added page
     *
     * Since no fields under version control are affected, this shouldn't add
     * any rows to version control database tables.
     *
     * @depends testUnpublishPage
     * @param Page $page
     * @return Page
     */
    public function testPublishPage(Page $page) {
        $page->removeStatus(Page::statusUnpublished);
        $page->save();
        return $page;
    }

    /**
     * Edit and unpublish previously added page
     *
     * Since no fields under version control are affected, this shouldn't add
     * any rows to version control database tables.
     *
     * @depends testPublishPage
     * @param Page $page
     * @return Page
     */
    public function testEditAndUnpublishPage(Page $page) {
        $page->addStatus(Page::statusUnpublished);
        $page->sidebar = "sidebar test";
        $page->save();
        return $page;
    }

    /**
     * Move previously added page 
     *
     * Since no fields under version control are affected, this shouldn't add
     * any rows to version control database tables.
     *
     * @depends testEditAndUnpublishPage
     * @param Page $page
     * @return Page
     */
    public function testMovePage(Page $page) {
        $page->parent = wire('pages')->get("/")->child();
        $page->save();
        return $page;
    }

    /**
     * Trash previously added page
     *
     * Since no fields under version control are affected, this shouldn't add
     * any rows to version control database tables.
     * 
     * @depends testMovePage
     * @param Page $page
     * @return Page
     */
    public function testTrashPage(Page $page) {
        $page->trash();
        return $page;
    }

    /**
     * Restore previously trashed page
     *
     * Since no fields under version control are affected, this shouldn't add
     * any rows to version control database tables.
     *
     * @depends testTrashPage
     * @param Page $page
     * @return Page
     */
    public function testRestorePage(Page $page) {
        $page->parent = wire('pages')->get("/");
        $page->save();
        return $page;
    }

    /**
     * Edit repeater item
     *
     * One row should be added to both version history database tables. Note
     * that pages_id for version_control_for_text_fields row should point to
     * the repeater item (RepeaterPage), not to the page it's on.
     *
     * @depends testRestorePage
     * @param Page $page
     * @return Page
     */
    public function testEditRepeaterField(Page $page) {
        $item = $page->repeater->getNew();
        $item->title = "repeater title";
        $page->save('repeater');
        self::$data[] = array((string) $item->id, "1", "40", "guest", "data", "repeater title");
        return $page;
    }

    /**
     * Edit images field
     * 
     * This should add two rows to revisions table, two rows to data table and
     * two rows to files table.
     * 
     * @depends testEditRepeaterField
     * @param Page $page
     * @return Page
     */
    public function testEditFieldtypeImageMulti(Page $page) {
        $file = __DIR__ . "/../SIPI_Jelly_Beans.png";
        $filename = hash_file('sha1', $file) . "." . strtolower(basename($file));
        $page->_filedata_timestamp = time();
        $filedata = json_encode(array(
            'filename' => $filename,
            'description' => '',
            'modified' => $page->_filedata_timestamp,
            'created' => $page->_filedata_timestamp,
            'tags' => '',
        ));
        $page->images = $file;
        $page->images = $file;
        $page->save();
        self::$data[] = array((string) $page->id, (string) wire('fields')->get('images')->id, "40", "guest", "0.data", $filedata, $filename, "image/png", "91081");
        self::$data[] = array((string) $page->id, (string) wire('fields')->get('images')->id, "40", "guest", "1.data", str_replace('.png', '-1.png', $filedata), str_replace('.png', '-1.png', $filename), "image/png", "91081");
        return $page;
    }

    /**
     * Fetch a snapshot for page
     *
     * To test snapshots properly, we need to make sure that there's enough time
     * between previous changes and current state, which is why we'll use sleep
     * function. Back and forth testing is mostly just a precaution.
     *
     * @depends testEditFieldtypeImageMulti
     * @param Page $page
     * @return Page
     */
    public function testSnapshot(Page $page) {

        $java = null;
        if (method_exists(wire('languages'), 'reloadLanguages')) {
            $java = wire('languages')->get('java');
            $default = wire('languages')->get('default');
            $page->text_language = 'placeholder';
            $page->save();
            self::$data[] = array((string) $page->id, (string) wire('fields')->get('text_language')->id, "40", "guest", "data{$default}", "placeholder");
        }

        // Snapshots are based on time and API operations happen very fast, so
        // we'll generate small gap here by making PHP sleep for a few seconds
        sleep(4);

        // Save current revision number for later use
        $starting_revision = $page->versionControlRevision;

        $page->title = "a test page 3";
        $page->name = $page->title;
        $page->body = "new body text";
        $item = $page->repeater->first();
        $item->title = "new repeater title";
        $file = __DIR__ . "/../SIPI_Jelly_Beans.png";
        $filename = hash_file('sha1', $file) . "." . strtolower(basename($file));
        $filedata_timestamp = time();
        $filedata = json_encode(array(
            'filename' => $filename,
            'description' => '',
            'modified' => $filedata_timestamp,
            'created' => $filedata_timestamp,
            'tags' => '',
        ));
        $page->images = $file;
        if ($java) {
            $page->text_language = "default language value";
            $page->text_language->setLanguageValue($java, 'since forever');
        }
        $page->save();
        $this->assertEquals($starting_revision+2, $page->versionControlRevision);
        self::$data[] = array((string) $page->repeater->first()->id, "1", "40", "guest", "data", "new repeater title");
        self::$data[] = array((string) $page->id, "1", "40", "guest", "data", "a test page 3");
        self::$data[] = array((string) $page->id, "76", "40", "guest", "data", "new body text");
        self::$data[] = array((string) $page->id, (string) wire('fields')->get('images')->id, "40", "guest", "0.data", str_replace($filedata_timestamp, $page->_filedata_timestamp, $filedata), $filename, "image/png", "91081");
        self::$data[] = array((string) $page->id, (string) wire('fields')->get('images')->id, "40", "guest", "1.data", str_replace(array('.png', $filedata_timestamp), array('-1.png', $page->_filedata_timestamp), $filedata), str_replace('.png', '-1.png', $filename), "image/png", "91081");
        self::$data[] = array((string) $page->id, (string) wire('fields')->get('images')->id, "40", "guest", "2.data", str_replace('.png', '-2.png', $filedata), str_replace('.png', '-2.png', $filename), "image/png", "91081");
        if ($java) {
            self::$data[] = array((string) $page->id, (string) wire('fields')->get('text_language')->id, "40", "guest", "data{$default}", "default language value");
            self::$data[] = array((string) $page->id, (string) wire('fields')->get('text_language')->id, "40", "guest", "data{$java}", "since forever");
        }

        $page->snapshot('-2 seconds');
        $this->assertEquals($starting_revision, $page->versionControlRevision);
        $this->assertEquals('a test page 2', $page->title);
        $this->assertEquals('body text', $page->body);
        $this->assertEquals('repeater title', $page->repeater->first()->title);
        $this->assertEquals($filename . "|" . str_replace(".png", "-1.png", $filename), $page->images);
        if ($java) {
            $this->assertEquals('placeholder', (string) $page->text_language);
            $this->assertEquals('since 1995', $page->text_language->getLanguageValue($java));
        }

        $page->snapshot();
        $this->assertEquals($starting_revision+2, $page->versionControlRevision);
        $this->assertEquals('a test page 3', $page->title);
        $this->assertEquals('new body text', $page->body);
        $this->assertEquals('new repeater title', $page->repeater->first()->title);
        $this->assertEquals($filename . "|" . str_replace(".png", "-1.png", $filename) . "|" . str_replace(".png", "-2.png", $filename), $page->images);
        if ($java) {
            $this->assertEquals('default language value', (string) $page->text_language); 
            $this->assertEquals('since forever', $page->text_language->getLanguageValue($java));
        }

        $page->title = "a test page 4";
        $page->repeater->first()->title = "repeater title 2";

        $page->snapshot('-2 seconds');
        $this->assertEquals($starting_revision, $page->versionControlRevision);
        $this->assertEquals('a test page 2', $page->title);
        $this->assertEquals('body text', $page->body);
        $this->assertEquals('repeater title', $page->repeater->first()->title);
        $this->assertEquals($filename . "|" . str_replace(".png", "-1.png", $filename), $page->images);
        if ($java) {
            $this->assertEquals('placeholder', (string) $page->text_language); 
            $this->assertEquals('since 1995', $page->text_language->getLanguageValue($java));
        }

        $page->snapshot(null, $starting_revision+2);
        $this->assertEquals($starting_revision+2, $page->versionControlRevision);
        $this->assertEquals('a test page 3', $page->title);
        $this->assertEquals('new body text', $page->body);
        // note: at the moment repeater is one revision behind page; is this OK?
        $this->assertEquals('repeater title', $page->repeater->first()->title);
        $this->assertEquals($filename . "|" . str_replace(".png", "-1.png", $filename) . "|" . str_replace(".png", "-2.png", $filename), $page->images);
        if ($java) {
            $this->assertEquals('default language value', (string) $page->text_language); 
            $this->assertEquals('since forever', $page->text_language->getLanguageValue($java));
        }

        // Reset page (but store filedata timestamp for later use)
        $filedata_timestamp = $page->_filedata_timestamp; 
        wire('pages')->uncache($page);
        $page = wire('pages')->get($page->id);
        $page->_filedata_timestamp = $filedata_timestamp;
        $this->assertEquals($starting_revision+2, $page->versionControlRevision);

        return $page;

    }

    /**
     * Revert (or restore) page to earlier revision
     * 
     * @depends testSnapshot
     * @param Page $page
     * @return Page
     */
    public function testRevertPage(Page $page) {
        $page->snapshot('-2 seconds');
        $file = __DIR__ . "/../SIPI_Jelly_Beans.png";
        $filename = hash_file('sha1', $file) . "." . strtolower(basename($file));
        $filedata = json_encode(array(
            'filename' => $filename,
            'description' => '',
            'modified' => $page->_filedata_timestamp,
            'created' => $page->_filedata_timestamp,
            'tags' => '',
        ));
        $this->assertEquals('a test page 2', $page->title);
        $this->assertEquals('body text', $page->body);
        $this->assertEquals('repeater title', $page->repeater->first()->title);
        $this->assertEquals($filename . "|" . str_replace(".png", "-1.png", $filename), (string) $page->images);
        self::$data[] = array((string) $page->id, "1", "40", "guest", "data", "a test page 2");
        self::$data[] = array((string) $page->id, "76", "40", "guest", "data", "body text");
        self::$data[] = array((string) $page->id, (string) wire('fields')->get('images')->id, "40", "guest", "0.data", $filedata, $filename, "image/png", "91081");
        self::$data[] = array((string) $page->id, (string) wire('fields')->get('images')->id, "40", "guest", "1.data", str_replace('.png', '-1.png', $filedata), str_replace('.png', '-1.png', $filename), "image/png", "91081");
        $page->save();
        return $page;
    }

    /**
     * Edit page type field
     *
     * This should add one row to both version history database tables.
     *
     * @depends testRevertPage
     * @param Page $page
     * @return Page
     */
    public function testEditFieldtypePage(Page $page) {
        $page->page = wire('pages')->get('/about/');
        $page->save();
        self::$data[] = array((string) $page->id, (string) wire('fields')->get('page')->id, "40", "guest", "data", "1001");
        return $page;
    }

    /**
     * Add new page for additional image field testing
     *
     * This should add one row to revisions table and one row to data table.
     *
     * @depends testEditFieldtypeImageMulti
     * @returns Page
     */
    public function testAddImageTestPage() {
        $page = new Page;
        $page->parent = wire('pages')->get('/');
        $page->template = wire('templates')->get('basic-page');
        $page->title = "a test page 6";
        $page->save();
        self::$data[] = array((string) $page->id, "1", "40", "guest", "data", "a test page 6");
        return $page;
    }

    /**
     * Add new page and edit image field on it
     * 
     * This should add one row to revisions table and one row to data table. It
     * should *not* add new row to files table.
     * 
     * @depends testAddImageTestPage
     * @param Page $page
     * @return Page
     */
    public function testEditFieldtypeImage(Page $page) {
        $file = __DIR__ . "/../SIPI_Jelly_Beans.png";
        $filename = hash_file('sha1', $file) . "." . strtolower(basename($file));
        $filedata = array(
            'filename' => $filename,
            'description' => '',
            'modified' => time(),
            'created' => time(),
        );
        $page->image = $file;
        $page->save();
        self::$data[] = array((string) $page->id, (string) wire('fields')->get('image')->id, "40", "guest", "0.data", json_encode($filedata), $filename, "image/png", "91081");
        return $page;
    }

    /**
     * Delete page created earlier for image field testing
     * 
     * This should remove the last two rows of data from version control
     * database tables.
     * 
     * @depends testEditFieldtypeImage
     * @param Page $page
     */
    public function testDeleteImageTestPage(Page $page) {
        $page->delete();
        array_pop(self::$data);
        array_pop(self::$data);
    }

    /**
     * Delete previously added main test page
     *
     * This operation should clear all previously added rows from version
     * control database table.
     *
     * Note: won't pass until ProcessWire issue #368 is resolved.
     *
     * @depends testEditFieldtypePage
     * @param Page $page
     */
    public function testDeletePage(Page $page) {
        $page->delete();
        self::$data = array();
    }

    /**
     * Create another page with a template NOT under version control
     *
     * Since this page isn't under version control, no rows should be saved into
     * version control database tables.
     *
     * @depends testDeletePage
     * @return Page
     */
    public function testAddNonVersionedPage() {
        $page = new Page;
        $page->parent = wire('pages')->get('/');
        $page->template = wire('templates')->get('sitemap');
        $page->title = "a test page 5";
        $page->save();
        return $page;
    }

    /**
     * Edit previously created non-versioned page
     *
     * Just like previous test, no rows should appear into version control
     * database tables.
     * 
     * @depends testAddNonVersionedPage
     * @param Page $page
     * @return Page
     */
    public function testEditNonVersionedPage(Page $page) {
        $page->title = "a test page 6";
        $page->summary = "summary text";
        $page->save();
        return $page;
    }

    /**
     * Attempt to return non-versioned page to earlier version
     *
     * Since this page isn't under version control, snapshot should not affect
     * page in any way.
     *
     * @depends testAddNonVersionedPage
     * @param Page $page
     * @return Page
     */
    public function testSnapshotOnNonVersionedPage(Page $page) {

        // Snapshots are based on time and API operations happen very fast, so
        // we'll generate small gap here by making PHP sleep for a few seconds
        sleep(4);

        $page->title = "a test page 3";
        $page->name = $page->title;
        $page->summary = "new summary text";
        $page->save();

        $page->snapshot('-2 seconds');
        $this->assertEquals('a test page 3', $page->title);
        $this->assertEquals('new summary text', $page->summary);

        $page->snapshot();
        $this->assertEquals('a test page 3', $page->title);
        $this->assertEquals('new summary text', $page->summary);

        $page->title = "a test page 4";
        $page->snapshot('-2 seconds');
        $this->assertEquals('a test page 4', $page->title);
        $this->assertEquals('new summary text', $page->summary);

        return $page;

    }

    /**
     * Delete previously created non-versioned page
     *
     * Since this page isn't under version control, once again nothing should
     * change in version control database tables.
     *
     * @depends testSnapshotOnNonVersionedPage
     * @param Page $page
     */
    public function testDeleteNonVersionedPage(Page $page) {
        $page->delete();
    }

    /**
     * Make sure that module is uninstallable
     *
     * @depends testInstallModule
     * @param string $module_name
     * @return string module name
     */
    public function testModuleIsUninstallable($module_name) {
        $this->assertTrue(wire('modules')->isUninstallable($module_name), "This isn't necessarily a sign of real error, as support for installing and uninstalling modules during one request is a PW 2.4+ feature");
        return $module_name;
    }

    /**
     * Uninstall module
     *
     * @depends testModuleIsUninstallable
     * @param string $module_name
     */
    public function testUninstallModule($module_name) {
        wire('modules')->uninstall($module_name);
        $this->assertFalse(wire('modules')->isInstalled($module_name));
    }

}