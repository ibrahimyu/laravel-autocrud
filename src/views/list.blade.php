@layout('crud_default')

@section('title')
	{{ $page_title }}
@endsection

@section('autocrud')
	<div id="autocrud">
		<p>
			@if (isset($icon))
			<img class="title-icon" src="{{ $icon }}"/>
			@endif
			<h3>{{ $page_title }}</h3>
		</p>
		<p style="clear: both"></p>
		<div>

			<div class="autocrud-header" style="padding-top: 0">
				@if ($can_insert)
				<a id="crud-add-btn" class="btn btn-primary fontfix" href="{{ $base_url }}/add">
					<i class="icon-plus icon-white"></i>
					{{ __('autocrud::autocrud.add-subject', array('subject' => $subject)) }}
				</a>
				@endif
				<span class="pull-right">
					{{ Form::open( $base_url . '/search', 'get', array('class' => 'form-search')) }}
						{{ Form::text('term', '', array('class' => 'input-medium search-query', 'placeholder' => __('autocrud::autocrud.keyword'), 'id' => 'ac_search_term')) }}
						{{ Form::submit('<i class="icon-search"></i> ' . __('autocrud::autocrud.find'), array('class' => 'btn fontfix', 'id' => 'ac_search_btn')) }}
					{{ Form::close() }}
				</span>
			</div>

			@if (Session::has('success_message'))
				<div class="alert alert-success shift-down fade-after-5secs">
					{{ Session::get('success_message') }}
				</div>
			@endif

			<div id="ac-container">
				<div id="ac-table-container">
					<table id="ac-table" class="table table-bordered autocrud-table">
						<thead>
							<?php foreach ($columns as $column): ?>
								<th style="text-align: center">
									@if (Input::get('sort') == $column)
										@if (Input::get('rev') == 1)
											<a href="?sort={{ $column }}" class="sort-handle ac-link">
											{{ $display[$column] }}
											<i class="icon-chevron-down"></i>
										@else
											<a href="?sort={{ $column }}&rev=1" class="sort-handle ac-link">
											{{ $display[$column] }}
											<i class="icon-chevron-up"></i>
										@endif
									@else
										<a href="?sort={{ $column }}" class="sort-handle ac-link">
										{{ $display[$column] }}
									@endif
									</a>
								</th>
							<?php endforeach; ?>
								<th style="width: 60px"></th>
						</thead>
						<tbody id="ac-data">
							@foreach ($result as $row)
							<tr>
								@foreach ($columns as $column)
									<?php $column = strtolower($column);
										if (isset($callback_column[$column])) {
											$row->$column = $callback_column[$column]($row->$column);
										}
										$pmk = strtolower($primary_key);
										$pmk = $row->$pmk;
									?>
									@if ($enable_view && $column == $summary_field)
										<td><a href="{{ $base_url }}/{{ $pmk }}">{{ $row->$column }}</a></td>
									@else
										<td>{{ $row->$column }}</td>
									@endif
								@endforeach
								<td style="text-align: right">
									@if ($can_edit)
									<a href="{{ $base_url }}/edit/{{ $pmk }}" class="crud-edit-btn btn btn-mini" title="{{ __('autocrud::autocrud.edit') }}" data-id="{{ $pmk }}"><span><i class="icon-pencil"></i></span></a>
									@endif
									
									@if ($can_delete)
									<a href="#" class="delete-btn btn btn-mini" title="{{ __('autocrud::autocrud.delete') }}" data-id="{{ $pmk }}" data-summary="{{ $row->$summary_field }}"><span><i class="icon-remove"></i></span></a>
									@endif
								</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				{{ $links }}
				</div>
			</div>
		</div>

		<div id="delete-confirm" class="modal hide fade">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
				<h2>{{ __('autocrud::autocrud.confirm-deletion') }}</h2>
			</div>
			<div class="modal-body">
				<p>{{ __('autocrud::autocrud.delete-confirm') }} <b><span id="confirm-summary"></span></b>?</p>
			</div>
			<div class="modal-footer">
				<button class="btn" data-dismiss="modal" aria-hidden="true">Cancel</button>
				<a id="do-delete" class="btn btn-danger" href="#">Delete</a>
			</div>
		</div>

		<div id="crud-ctxtform" class="modal hide fade" style=""></div>
		<script type="text/javascript" src="/asset/autocrud.js"></script>
		@if (Input::has('sort'))
		<script>
			$('.pagination li a').attr('href', function() { return $(this).attr('href') + '&sort={{ Input::get("sort") }}' });
		</script>
		@endif

		@if (Input::has('term'))
		<script>
			$('.pagination li a').attr('href', function() { return $(this).attr('href') + '&term=' + {{ Input::get('term') }} });
		</script>
		@endif
	</div>
@endsection