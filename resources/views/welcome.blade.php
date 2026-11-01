@extends('layouts.app')

@section('content')
<div class="container">
    <div class="text-center">
        <h1>Welcome to Photo Frame Manager</h1>
        <p class="lead">Manage your frames and arrange images easily.</p>
        <a href="{{ route('frames.index') }}" class="btn btn-primary btn-lg">Get Started</a>
    </div>
</div>
@endsection