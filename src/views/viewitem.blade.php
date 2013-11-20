@layout('crud_default')

@section('autocrud')
	<div class="row-fluid boxed white">
		<h2>{{ $row->$summary_field }}</h2>
		<div class="">
			<a href="{{ $base_url }}/print/{{ $primary_key_value }}" target="_blank" class="btn btn-primary">
				<i class="icon-print icon-white"></i>
				{{ __('autocrud::autocrud.print', array('subject' => $subject)) }}
			</a>
			<a href="{{ $base_url }}/edit/{{ $primary_key_value }}?next={{ urlencode(URL::full()) }}" class="btn">
				<i class="icon-edit"></i>
				{{ __('autocrud::autocrud.edit', array('subject' => $subject)) }}
			</a>
			<a href="{{ $base_url }}" class="btn pull-right">
				<i class="icon-backward"></i>
				{{ __('autocrud::autocrud.back') }}
			</a>
			@yield('actions')
		</div>
	</div>

	@yield('view-item')
@endsection