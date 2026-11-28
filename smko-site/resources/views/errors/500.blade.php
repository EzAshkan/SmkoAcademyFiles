@extends('errors.layout')
@section('title', 'Something went wrong')
@section('code', '500 · Something went wrong')
@section('heading', 'Something went wrong on our side')
@section('message', "We've been notified. Please try again in a minute.")
@section('illustration') @include('errors._scene', ['icon' => 'spark']) @endsection
@section('content') @include('errors._buttons', ['refresh' => true]) @endsection
