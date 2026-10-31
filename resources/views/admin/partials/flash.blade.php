@session('status')
    <x-moonshine::alert type="success">{{ $value }}</x-moonshine::alert>
@endsession
@if ($errors->any()) <x-moonshine::alert type="error">{{ $errors->first() }}</x-moonshine::alert> @endif
