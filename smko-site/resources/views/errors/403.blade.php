@extends('errors.layout')
@section('title', 'No access')
@section('code', '403 · No access')
@section('heading', "You don't have access to this folder")
@section('message', 'Ask SMKO Academy if you think you should.')
@section('illustration') @include('errors._scene', ['icon' => 'lock']) @endsection
@section('content')
    @include('errors._buttons')
    <a href="https://t.me/techsupportsa" target="_blank" rel="noopener" class="mt-6 text-sm font-semibold text-violet-600 hover:text-violet-500 dark:text-violet-400">Contact SMKO Academy on Telegram: @techsupportsa</a>
@endsection
