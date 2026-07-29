@extends('errors.layout')

@section('title', __('Not Found'))
@section('code', '404')
@section('header_text', "Oops.  We missed that page.")
@section('gif', 'missed.gif')
@section('detail', "The page you requested could not be found.")
