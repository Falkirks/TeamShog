<?php
namespace water\page;

use water\session\SessionStore;

class view extends Page{
    public function showPage($message = false){
        $user = SessionStore::getCurrentSession();
        echo $this->getTemplateEngine()->render($this->getTemplateSnip("page"), [
            "title" => "View",
            "content" => $this->getTemplateEngine()->render($this->getTemplate(), [
                "message" => ($message === false ? false : $message),
                "user" => $user
            ])
        ]);
    }
    public function hasPermission(){
        return true;
    }
}