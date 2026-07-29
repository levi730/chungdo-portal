@extends('errors.layout')

@section('title', __('Unauthorized'))
@section('code', '401')

@section('header_text', "Unauthorized")
@section('gif', 'kfh_no.gif')
@section('detail', "This request has not been completed because it lacks valid authentication credentials for the requested resource.")
