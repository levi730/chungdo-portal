@extends('errors.layout')

@section('title', __('Forbidden'))
@section('code', '403')

@section('header_text', "Forbidden")
@section('gif', 'kfh_no.gif')
@section('detail', __($exception->getMessage() ?: 'Request forbidden.'))
