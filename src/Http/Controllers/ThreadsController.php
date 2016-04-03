<?php

namespace History\Http\Controllers;

use History\Entities\Models\Thread;

class ThreadsController extends AbstractController
{
    /**
     * @return \Zend\Diactoros\Response\HtmlResponse
     */
    public function index()
    {
        $threads = Thread::latest()->get();

        return $this->render('threads/index.twig', [
            'threads' => $threads,
        ]);
    }
}