@extends('errors.minimal')
@section('code', '403')
@section('message', 'Access denied')
@section('detail', $exception->getMessage() ?: 'You do not have permission to view this page.')
