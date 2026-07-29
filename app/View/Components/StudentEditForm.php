<?php

namespace App\View\Components;

use Illuminate\View\Component;

class StudentEditForm extends Component
{
    public $user;

    public $schools;

    public $ranks;

    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct($user)
    {
        $this->user = $user;

        $this->schools = \App\Models\School::orderBy('name')->get();
        $this->ranks = \App\Models\Rank::orderBy('id')->get();
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.student-edit-form');
    }
}
