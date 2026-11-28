@extends('errors.layout')
@section('title', 'Page expired')
@section('code', '419 · Page expired')
@section('heading', 'Your session timed out')
@section('message', 'Please refresh and try again.')
@section('illustration') @include('errors._scene', ['icon' => 'hourglass']) @endsection
@section('content') @include('errors._buttons', ['refresh' => true]) @endsection
