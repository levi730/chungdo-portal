@extends('errors.layout')

@section('title', __('Too Many Requests'))
@section('code', '429')

@section('header_text', "A bit too much.")
@section('gif', 'burly_brawl.gif')
@section('detail', "You have sent too many requests in a given amount of time, and hit a rate limit.")
