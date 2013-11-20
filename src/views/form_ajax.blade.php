<?php $form_action = $edit_mode ? 'edit&id=' . $_GET['id'] : 'add'; ?>
{{ Form::horizontal_open( URL::current() . '?action=validate-' . $form_action) }}
<div class="modal-header">
	<h2>{{ $page_title }}</h2>
</div>
<div class="modal-body">
	@foreach($fields as $field)
		<div class="control-group {{ $errors->has($field) ? 'error' : '' }}">
			{{ $labels[$field] }}
			<div class="controls">
				{{ $inputs[$field] }}<br/>
				{{ $errors->has($field) ? $errors->first($field) : '' }}
			</div>
		</div>
	@endforeach
</div>
<div class="modal-footer form-actions">
	<button type="submit" class="btn btn-primary"><i class="icon-ok icon-white"></i>Save Changes</button>
	<button class="crud-close btn" style="width:120px" data-toggle="modal">Cancel</button>
</div>
{{ Form::close() }}