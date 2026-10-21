<?php
class backup_fredkicourseimport_activity_task extends backup_activity_task {
    protected function define_my_settings() {}
    protected function define_my_steps() {
        $this->add_step(new backup_fredkicourseimport_activity_structure_step('fredkicourseimport_structure', 'fredkicourseimport.xml'));
    }
    public static function encode_content_links($content) { return $content; }
}
