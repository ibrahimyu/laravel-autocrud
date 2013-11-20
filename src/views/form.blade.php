@layout('crud_default')

@section('title')
	{{ $page_title }}
@endsection

@section('autocrud')
	<div id="autocrud" class="span12">
		@if ($edit_mode)
			<img class="title-icon" src="/img/edit.png"/>
		@else
		    <img class="title-icon" src="/img/add.png"/>
		@endif
		<p class="underlined gradGreen">{{ $page_title }}</p>
		<div class="widget">
			<?php $form_action = $edit_mode ? 'edit/' . $primary_key_value : 'add'; ?>
			{{ Form::horizontal_open( '/' . URI::segment(1) . '/' . URI::segment(2) . '/' . 'validate-' . $form_action) }}
			
			@if (Input::get('next'))
				<input type="hidden" value="{{ Input::get('next') }}" name="next"/>
			@endif

			<?php foreach($fields as $field): ?>
				<?php echo Form::control_group($inputs[$field][0], $inputs[$field][1], $errors->has($field) ? 'error' : '', Form::block_help($errors->first($field))) . "\n\n";?>
			<?php endforeach; ?>

			<div class="form-actions" style="background: none">
				<button type="submit" class="btn btn-primary fixed-width"><i class="icon-ok icon-white"></i>
					{{ __('autocrud::autocrud.save-changes') }}
				</button>
				<a class="btn fixed-width" href="{{ Input::get('next', '/' . URI::segment(1) . '/' . URI::segment(2)) }}">
					{{ __('autocrud::autocrud.cancel') }}
				</a>
				@if (!$edit_mode)
				<label class="checkbox pull-right">
					<input type="checkbox" value="" name="keep-insert">
					Lock
				</label>
				@endif
			</div>

			{{ Form::close() }}
		</div>
	</div>
@endsection