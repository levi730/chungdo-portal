@extends('errors.layout')

@section('title', __('Server Error'))
@section('code', '500')

@section('header_text', "Oof. Server Error")
@section('gif', 'kfh_slap.gif')
@section('detail', "Something has caused an application error.  The error details have been logged and an administrator has been notified.")
