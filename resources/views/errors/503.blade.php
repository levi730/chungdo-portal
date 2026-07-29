@extends('errors.layout')

@section('title', __('Service Unavailable'))
@section('code', '503')

@section('header_text', "Server too busy")
@section('gif', 'burly_brawl.gif')
@section('detail', "This means the website's server is simply not available right now. Most of the time, it occurs because the server is too busy or because there's maintenance being performed on it.")
