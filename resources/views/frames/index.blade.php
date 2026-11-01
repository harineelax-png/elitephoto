@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Frames</h1>
    <a href="{{ route('frames.create') }}" class="btn btn-primary mb-3">Create New Frame</a>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Slots</th>
                <th>Images</th>
                <th>Template</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($frames as $frame)
                <tr>
                    <td>{{ $frame->id }}</td>
                    <td>{{ $frame->name }}</td>
                    <td>{{ $frame->total_slots }}</td>
                    <td>{{ $frame->images->count() }}</td>
                    <td>
                        @if($frame->template_path)
                            <img src="{{ asset('storage/'.$frame->template_path) }}" style="max-height:50px;">
                        @else
                            <span class="text-muted">None</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('frames.show', $frame) }}" class="btn btn-sm btn-info">View</a>
                        <a href="{{ route('frames.edit', $frame) }}" class="btn btn-sm btn-warning">Edit</a>
                        <form action="{{ route('frames.destroy', $frame) }}" method="POST" style="display:inline;">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')">Delete</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    {{ $frames->links() }}
</div>
@endsection