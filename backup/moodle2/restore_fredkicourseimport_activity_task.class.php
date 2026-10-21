<?php
class restore_fredkicourseimport_activity_task extends restore_activity_task {
    protected function define_my_settings() {}
    protected function define_my_steps() {
        $this->add_step(new restore_fredkicourseimport_activity_structure_step('fredkicourseimport_structure', 'fredkicourseimport.xml'));
    }
    public static function define_decode_contents() { return []; }
    public static function define_decode_rules() { return []; }
}
