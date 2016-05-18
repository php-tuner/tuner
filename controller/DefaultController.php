<?php
class DefaultController extends Controller{
    public function index(){
        $this->output(sprintf("<h1>default home from app(%s)</h1>", PROJECT));
    }   
}