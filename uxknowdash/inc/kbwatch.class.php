<?php
class PluginUxknowdashKBWatch extends CommonDBTM {
    public static $rightname = 'plugin_uxknowdash_view';
    public function getNameField() { return 'name'; }
 
    public function prepareInputForAdd($input) {
        if (strlen($input['name'] ?? '') < 3) {
            Session::addMessageAfterRedirect(__('Nom trop court', 'uxknowdash'), false, ERROR);
            return false;
        }
        return $input;
    }
 
    public function showForm($ID, array $options = []) {
        Html::openForm('', 'POST', '', ['id' => 'kbwatch_form']);
        echo "Nom : <input type='text' name='name'><br>";
        echo "Tag KB : <input type='text' name='kb_tag'><br>";
        echo "<button type='submit'>Créer</button>";
        Html::closeForm();
    }
}