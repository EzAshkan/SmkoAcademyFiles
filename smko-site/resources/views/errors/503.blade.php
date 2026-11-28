@extends('errors.layout')
@section('title', 'Maintenance')
@section('code', '503 · Maintenance')
@section('heading', "We're updating " . config('app.name', 'SMKO Files'))
@section('message', 'Back shortly. Thanks for your patience!')
@section('illustration') @include('errors._scene', ['icon' => 'tools']) @endsection
@section('content') @include('errors._buttons', ['refresh' => true]) @endsection
