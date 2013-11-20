<?php

/**
 * AutoCrud for Laravel
 *
 * Avoids redundancy of Create, Read, Update, and Delete operations. AutoCrud is enriched by several
 * smart features, including schema detector, smart display name, summary field detector, etc.
 * AutoCrud will also supports View, Print, and maybe, Export operations.
 *
 * Copyright (c) 2012 Ibrahim Yusuf
 *
 * @package		AutoCrud for Laravel
 * @author		Ibrahim Yusuf
 * @version		0.3
 * @license		MIT
 */
class AutoCrud
{
	protected $connection;
	protected $data = array();

	protected $show_primary_key = false;

	protected $fields = array();
	protected $relations = array();
	protected $validation_rules = array();
	protected $default_values = array();
	public $per_page = 10;

	protected $item_view;
	protected $print_view;
	public $form_view = null;

	protected $ajax = false;

	protected $callback_column = array();
	protected $callback_add = array();
	protected $callback_edit = array();
	protected $callback_input = array();
	protected $listeners = array();
	protected $callback_view = null;

	//protected $computed_column = array();

	protected $enable_notifications = false;

	public $can_insert = true;
	public $can_edit = true;
	public $can_delete = true;

	public $translation = false;
	public $translation_enable = 'en';
	public $translation_prefix = null;

	public $sort_by = null;
	public $sort_desc = null;

	public $query;

	public $base_segment = 2;

	public function __construct($table_name = null, $connection = null)
	{
		$this->data['fields'] = array();
		$this->data['columns'] = array();
		$this->data['enable_view'] = false;

		if ($connection)
		{
			$this->set_connection($connection);
		}

		if ($table_name)
		{
			$this->set_table($table_name);
		}
	}

	public function set_connection($connection)
	{
		$this->connection = $connection;
	}

	/**
	 * Initializes assets. Normally should invoked when render.
	 *
	 * @return void
	 */
	protected function init()
	{

	}

	/**
	 * Sets the table name for this instance, and exracts its table info.
	 *
	 * @return void
	 */
	public function set_table($table_name)
	{
		$this->table_name = $table_name;
		$this->get_table_info();
		$this->query = DB::connection($this->connection)->table($this->table_name);
	}

	/**
	 * Gets table info specified in $table_name.
	 */
	protected function get_table_info()
	{
		$this->extract_table_info();
	}

	/**
	 * Extracts table info. Currently only works on MySQL, because
	 * this function has not been tested on any other database.
	 *
	 * @return void
	 */
	protected function extract_table_info()
	{
		$query = DB::connection($this->connection)
					->query('SHOW COLUMNS FROM `' . $this->table_name . '`');

		$this->data['subject'] = Str::title(Str::singular(str_replace('_', ' ', $this->table_name)));

		foreach ($query as $table_column)
		{
			$this->fields[$table_column->field] = new stdClass();
			$data_type = explode('(', $table_column->type);
			$this->fields[$table_column->field]->type = $data_type[0];

			if (isset($data_type[1]))
			{
				if ($data_type[0] == 'enum' || $data_type[0] == 'set')
				{
					$this->fields[$table_column->field]->values = $this->get_enum_values($data_type[1]);
				}
				else
				{
					$this->fields[$table_column->field]->length = intval($data_type[1]);
				}
			}

			if ($table_column->key == 'PRI')
			{
				$this->set_primary_key($table_column->field);

				if (!$this->show_primary_key && $table_column->extra == 'auto_increment')
				{
					continue;
				}
			}

			if ($table_column->null == 'NO')
			{
				$this->fields[$table_column->field]->required = true;
				$this->validation_rules[$table_column->field] = 'required';

				if (!isset($this->data['summary_field']) && in_array($data_type[0], array('varchar', 'text')))
				{
					$this->set_summary_field($table_column->field);
				}
			}
			else
			{
				$this->fields[$table_column->field]->required = false;
			}

			if ($data_type[0] == 'decimal')
			{
				$this->field_type($table_column->field, 'money');
			}

			if ($data_type[0] == 'date')
			{
				$this->field_type($table_column->field, 'date');
			}

			if ($data_type[0] != 'timestamp')
			{
				$this->data['fields'][] = $table_column->field;
				$this->data['columns'][] = $table_column->field;
				$this->data['display'][$table_column->field] = $this->get_display_name($table_column->field);
			}
			else
			{
				$this->fields[$table_column->field]->required = false;
				$this->validation_rules[$table_column->field] = '';
			}

		}

		if (!isset($this->data['summary_field']))
			$this->set_summary_field($this->data['primary_key']);
	}

	/**
	 * Gets enum value from MySQL type string.
	 *
	 * @return array
	 */
	protected function get_enum_values($str)
	{
		$str = str_replace(array(')', "'"), '', $str);
		$str = explode(",", $str);
		
		foreach ($str as $val)
		{
			$ret[$val] = $val;
		}

		return $ret;
	}

	/**
	 * Renders AutoCrud.
	 *
	 * @param string $specific_action Show only specific action. Leave blank for default behavior.
	 * @param mixed $data Parameter to be passed on that action.
	 * @return View
	 */
	public function render($specific_action = null, $data = null)
	{
		$action = URI::segment(3, Input::get('action', 'list'));
		$primary_key = URI::segment(4, Input::get('id'));

		$this->init();

		$this->data['can_insert'] = $this->can_insert;
		$this->data['can_edit'] = $this->can_edit;
		$this->data['can_delete'] = $this->can_delete;

		$this->data['_instance'] = &$this;

		switch ($action)
		{
			case 'add' :
				return $this->can_insert ? $this->create_add_form() : Response::error(403);
			case 'validate-add' :
				return $this->validate_add();
			case 'edit' :
				return $this->can_edit ? $this->create_edit_form($primary_key) : Response::error(403);
			case 'validate-edit' :
				return $this->validate_edit($primary_key);
			case 'delete' :
				return $this->can_delete ? $this->delete($primary_key) : Response::error(403);
			case 'view' :
				return $this->create_view($primary_key);
			case 'print' :
				return $this->create_print($primary_key);
			case 'search' :
				if (Input::get('term'))
					return $this->create_search(Input::get('term'));
				else
					return $this->create_table();
			case 'list':
				return $this->create_table();
			default:
				return $this->create_view(URI::segment(3));
		}
	}

	/**
	 * Validates all inputs in add form. If validation success, will redirect to view with success message.
	 * Otherwise, will return to add form with errors and old input.
	 *
	 * @return Redirect
	 */
	protected function validate_add()
	{
		Input::flash();
		$input = Input::all();

		foreach ($this->callback_add as $field => $func)
		{
			$input[$field] = $func($input[$field]);
		}

		foreach ($this->default_values as $field => $default_value)
		{
			if ( ! isset($input[$field]))
				$input[$field] = $default_value;
		}

		$validation = Validator::make($input, $this->validation_rules);

		if ($validation->fails())
		{

			return Redirect::back()
					->with_errors($validation)
					->with_input();

		}
		else
		{
			
			foreach ($this->data['fields'] as $field)
			{
				$values[$field] = e(Input::get($field));
			}
			
			$id = DB::connection($this->connection)
					->table($this->table_name)
					->insert_get_id($values);

			$this->fire('insert', array($id => $values));

			if ($this->enable_notifications)
			{
				$values['id'] = $id;
				$this->notify('insert', $values[$this->data['summary_field']]);
			}
			
			$next_url = Input::get('next', $this->base_url());

			return Redirect::to($next_url)
				->with('success_message', __('autocrud::autocrud.success-update'));
		}
	}

	/**
	 * Validates all inputs in edit form. If validation success, will redirect to view with success message.
	 * Otherwise, will return to edit form with errors and old input.
	 */
	protected function validate_edit($id)
	{
		Input::flash();
		$input = Input::all();

		foreach ($this->callback_edit as $field => $func)
		{
			$input[$field] = $func($input[$field]);
		}

		$validation = Validator::make($input, $this->validation_rules);

		if ($validation->fails())
		{

			return Redirect::back()
					->with_errors($validation)
					->with_input();

		}
		else
		{
			foreach ($this->data['fields'] as $field)
			{
				$values[$field] = e(Input::get($field));
			}

			DB::connection($this->connection)
				->table($this->table_name)
				->where($this->data['primary_key'], '=', $id)
				->update($values);

			$this->fire('update', array($id => $values));

			if ($this->enable_notifications)
			{
				$values['id'] = $id;
				$this->notify('update', $values[$this->data['summary_field']]);
			}

			$next_url = Input::get('next', $this->base_url());

			return Redirect::to($next_url)
				->with('success_message', __('autocrud::autocrud.success-update'));
		}
	}

	/**
	 * Creates a standard, paginated table view with all data.
	 */
	protected function create_table()
	{
		if (isset($this->data['title_list']))
			$this->data['page_title'] = $this->data['title_list'];
		//else if ($this->translation && (Session::get('lang') == $this->translation_enable))
		//	$this->data['page_title'] = Str::plural(__($this->translation_prefix . '.' .  $this->data['subject']));
		else
		{
			$this->data['page_title'] = Str::plural($this->data['subject']);
		}

		if ( ! isset($this->data_source))
		{
			$this->data_source = $this->get_data()->paginate($this->per_page);
		}

		$this->data['result'] = $this->data_source->results;
		$this->data['links'] = $this->data_source->links();
		$this->data['callback_column'] = $this->callback_column;

		$this->data['base_url'] = $this->base_url();

		return View::make('autocrud::list', $this->data);
	}

	/**
	 * Creates a paginated table view with search results.
	 */
	protected function create_search($query)
	{
		$db = $this->get_data();

		foreach ($this->data['columns'] as $column)
		{
			if (isset($this->relations[$column]))
				$rel = $this->relations[$column]['related_table'] . '.' . $this->relations[$column]['display_field'];
			else
				$rel = $this->table_name . '.' . $column;

			$db->or_where($rel, 'LIKE', '%' . $query . '%');
		}

		$this->data_source = $db->paginate($this->per_page);
		return $this->create_table();
	}

	/**
	 * Creates an add form.
	 */
	protected function create_add_form()
	{
		if (isset($this->data['title_add']))
			$this->data['page_title'] = $this->data['title_add'];
		else
			$this->data['page_title'] = __('autocrud::autocrud.add-subject', array('subject' => $this->data['subject']));
		
		$this->data['edit_mode'] = false;

		foreach ($this->data['fields'] as $field)
		{
			$this->data['inputs'][$field] = $this->get_input($field, Input::old($field, Input::get($field)));
		}

		if ( $this->form_view )
			return View::make($this->form_view, $this->data);

		if ( ! $this->ajax)
		{
			return View::make('autocrud::form', $this->data);
		}

		return View::make('autocrud::form_ajax', $this->data);
	}

	/**
	 * Creates an edit form, with specified primary key value.
	 */
	protected function create_edit_form($primary_key_value)
	{
		$this->data['values'] = get_object_vars(
			DB::connection($this->connection)
				->table($this->table_name)
				->find($primary_key_value)
			);

		if (isset($this->data['title_edit']))
			$this->data['page_title'] = $this->data['title_edit'];
		else
			$this->data['page_title'] = __('autocrud::autocrud.edit-subject', array('subject' => $this->data['subject']));
		
		$this->data['primary_key_value'] = $primary_key_value;
		$this->data['edit_mode'] = true;

		foreach ($this->data['fields'] as $field)
		{
			if (Input::had($field))
			{
				$this->data['inputs'][$field] = $this->get_input($field, Input::old($field));
			}
			else
			{
				$this->data['inputs'][$field] = $this->get_input($field, $this->data['values'][strtolower($field)]);
			}
		}

		if ( $this->form_view )
			return View::make($this->form_view, $this->data);

		if ( ! $this->ajax)
			return View::make('autocrud::form', $this->data);

		return View::make('autocrud::form_ajax', $this->data);
	}

	/**
	 * Deletes an item specified primary key value.
	 */
	protected function delete($id)
	{
		/*if (Request::method() != 'POST')
			return "Method must be post.";*/

		$values = DB::connection($this->connection)->table($this->table_name)->find($id);

		if ($this->enable_notifications && $values)
		{
			$summary = $this->data['summary_field'];
			$this->notify('delete', $values->$summary);
		}

		DB::connection($this->connection)
			->table($this->table_name)
			->delete($id);

		$next_url = Input::get('next', URL::current());

		return Redirect::to($next_url);
	}

	protected function create_view($id)
	{

		if ( ! $id)
			return Redirect::back();

		$data['row'] = $this->get_data()
				->where($this->table_name . '.' . $this->data['primary_key'], '=', $id)
				->first();

		$data['primary_key'] = $this->data['primary_key'];
		$data['summary_field'] = $this->data['summary_field'];
		$data['primary_key_value'] = $id;
		$data['subject'] = $this->data['subject'];
		$data['base_url'] = $this->base_url();

		if (isset($this->data['view_data']))
			$data = array_merge($data, $this->data['view_data']);

		if ( ! $this->item_view)
			$this->item_view = 'autocrud::viewitem';

		return View::make($this->item_view, $data);

	}

	protected function create_print($id)
	{

		if ( ! $id)
			return Redirect::back();

		$data['row'] = $this->get_data()
				->where($this->table_name . '.' . $this->data['primary_key'], '=', $id)
				->first();

		$data['primary_key'] = $this->data['primary_key'];
		$data['summary_field'] = $this->data['summary_field'];
		$data['primary_key_value'] = $id;

		if ( ! $this->print_view )
			$this->print_view = 'autocrud::viewitem';

		return View::make($this->print_view, $data);

	}

	/**
	 * Gets a query object with necessary properties, depending on relationships in the table.
	 *
	 * @return query
	 */
	protected function get_data()
	{
		$columns = array();
		$columns[] = $this->table_name . '.*';

		if ( ! $this->show_primary_key)
		{
			$columns[] = $this->table_name . '.' . $this->data['primary_key'];
		}

		foreach ($this->data['columns'] as $column)
		{

			if (isset($this->relations[$column]))
			{
				$relation = $this->relations[$column];

				if ($relation['type'] == '1-n')
				{
					$this->query->left_join($relation['related_table'], $this->table_name . '.' . $column, '=', $relation['related_table'] . '.' . 'Id');
					$columns[] = $relation['related_table'] . '.' . $relation['display_field'] . ' AS ' . $column;
				}

			}
			else
			{
				$columns[] = $this->table_name . '.' . $column;
			}
		}

		$this->query->select($columns);

		if (Input::get('sort') && (in_array($this->table_name . '.' . Input::get('sort'), $columns) || isset($this->relations[Input::get('sort')])) )
		{
			$sort_field = Input::get('sort');

			$mode = Input::get('rev') ? 'desc' : 'asc';

			if (isset($this->relations[$sort_field]))
			{
				$relation = $this->relations[$sort_field];
				$this->query->order_by($relation['related_table'] . '.' . $relation['display_field'], $mode);
			}
			else
			{
				$this->query->order_by($this->table_name . '.' . $sort_field, $mode);
			}
		}
		else if ($this->sort_by)
		{
			$sort_field = $this->sort_by;
			$mode = $this->sort_desc ? 'desc' : 'asc';

			if (isset($this->relations[$sort_field]))
			{
				$relation = $this->relations[$sort_field];
				$this->query->order_by($relation['related_table'] . '.' . $relation['display_field'], $mode);
			}
			else
			{
				$this->query->order_by($this->table_name . '.' . $sort_field, $mode);
			}
		}

		return $this->query;
	}

	/**
	 * Gets html input element to be used in add and edit form for specified field.
	 *
	 * @return string
	 */
	protected function get_input($field_name, $value = null)
	{
		$label = Form::label($field_name, 
			$this->data['display'][$field_name],
			array('class' => $this->fields[$field_name]->required ? 'control-label required' : 'control-label'));

		if (isset($this->relations[$field_name]))
		{
			$relation = $this->relations[$field_name];

			switch ($relation['type'])
			{
				case '1-n' :
					$query = DB::connection($this->connection)
								->table($relation['related_table']);

					if ($relation['where'])
						$query->raw_where($relation['where']);

					$query_result = $query->get(array('id', $relation['display_field']));
					
					$options = array();

					if ( ! $this->fields[$field_name]->required)
						$options[] = '';

					foreach ($query_result as $result)
					{
						$options[$result->id] = $result->$relation['display_field'];
					}

					$input = Form::select($field_name, $options, $value);
					break;

				case 'n-n' :
					$options = DB::connection($this->connection)->table($relation['related_table'])->get('id', $relation['display_field']);
					$input = Form::select($field_name, $options);
					break;

				case '1-1' :
					$query = DB::connection($this->connection)->table($relation['related_table']);

					if ($relation['where'])
						$query->raw_where($relation['where']);

					$query->result = $query->where_null($field_name)->get('id', $relation['display_field']);

					break;
				case 'children' :
					break;
			}

		}
		else
		{
			// no relation, get field info
			switch ($this->fields[$field_name]->type)
			{
				case 'money' :
					$input = Form::prepend(Form::text($field_name, $value, array('style' => 'text-align:right')), 'IDR');
					break;
				case 'date' :
					$input = Form::append(Form::text($field_name, $value, array('data-format' => 'yyyy-MM-dd')), '<i class="icon-calendar" data-date-icon="icon-calendar"></i>', 'date-picker');
					break;
				case 'time' :
					$input = Form::text($field_name, $value, array('class' => 'time-picker input-small'));
					break;
				case 'datetime' :
				case 'timestamp' :
					$input = Form::append(Form::text($field_name, $value, array('data-format' => 'yyyy-MM-dd hh:mm:ss')), '<i class="icon-time" data-date-icon="icon-time"></i>', 'datetime-picker');
					break;
				case 'enum' :
					$input = Form::select($field_name, $this->fields[$field_name]->values, $value, array('class' => 'chzn-select', 'data-placeholder' => __('autocrud::autocrud.select-an-option')));
					break;
				case 'set' :
					$input = Form::select($field_name, $this->fields[$field_name]->values, $value, array('class' => 'chzn-select', 'multiple' => 'multiple'));
					break;
				case 'text' :
					$input = Form::xxlarge_textarea($field_name, $value, array('rows' => '4', 'class' => 'input-xlarge'));
					break;
				case 'int' :
					$input = Form::text($field_name, $value, array('class' => 'input_small', 'style' => 'text-align: right'));
					break;
				case 'hidden':
					switch ($this->fields[$field_name]->mode)
					{
						case 2:
							$input = Form::xlarge_text($field_name, $value ? $value : $this->fields[$field_name]->default_value);
							break;
						case 1:
							$input = Form::uneditable($value ? $value : $this->fields[$field_name]->default_value);
							break;
						case 0:
							return Form::hidden($field_name, $value ? $value : $this->fields[$field_name]->default_value);
					}
					break;
				default:
					$input = Form::text($field_name, $value, array('class' => 'input-xlarge'));
			}
		}

		if (isset($this->callback_input[$field_name]))
		{
			$input = $this->callback_input[$field_name]($input);
		}

		return array($label, $input);

	}

	protected function get_display_name($field_name)
	{
		if ($this->translation && (Session::get('lang') == $this->translation_enable))
			$temp = __($this->translation_prefix . '.' . $field_name);
		else
			$temp = $field_name;

		$data['display'][$field_name] = Str::title(str_replace('_', ' ', $temp));

		return $data['display'][$field_name];
	}

	/**
	 * Set columns to be shown on grid view.
	 *
	 * @return void
	 */
	public function set_columns(array $columns)
	{
		$this->data['columns'] = $columns;
	}

	/**
	 * Sets primary key for the table. Normally AutoCrud can detect primary keys.
	 * This function should be called if the table does not have primary key, to be used in update operation.
	 *
	 * @return void
	 */
	public function set_primary_key($primary_key)
	{
		$this->data['primary_key'] = $primary_key;
	}

	/**
	 * Sets the data source for this instance. Useful if we want to use custom queries.
	 *
	 * @return void
	 */
	public function set_data_source($source)
	{
		$this->data_source = $source;
	}

	/**
	 * Sets the subject, or "item name" for this instance.
	 *
	 * @return void
	 */
	public function set_subject($subject)
	{
		$this->data['subject'] = $subject;
	}

	/**
	 * Set the fields to be used on add and edit forms.
	 * Equivalent to calling set_add_fields and set_edit_fields together.
	 *
	 * @return void
	 */
	public function set_fields($fields)
	{
		$this->data['fields'] = $fields;
	}

	/**
	 * Set the fields to be used on add form.
	 *
	 * @return void
	 */
	public function set_add_fields(array $fields)
	{
		$this->data['add_fields'] = $fields;
	}

	/**
	 * Set the fields to be used on edit form.
	 *
	 * @return void
	 */
	public function set_edit_fields(array $fields)
	{
		$this->data['edit_fields'] = $fields;
	}

	/**
	 * Changes data type for specified fields. Useful to override the default inputs.
	 *
	 * @return void
	 */
	public function field_type($field_name, $field_type, $values = null)
	{
		switch ($field_type)
		{
			case 'money':
				$this->callback_column($field_name, function($x)
				{
					return I::m($x);
				});
				break;
			case 'date':
				$this->callback_column($field_name, function($x)
				{
					return date('d M Y', strtotime($x));
				});
				break;
			case 'enum':
				$this->callback_column($field_name, function($x) use ($values)
				{
					return $values[$x];
				});
		}
		$this->fields[$field_name]->type = $field_type;

		if ($values)
			$this->fields[$field_name]->values = $values;
	}

	/**
	 * Sets specified fields to be required.
	 *
	 * @return void
	 */
	public function set_required(array $fields)
	{
		$this->required_fields = $fields;
	}

	/**
	 * Sets a one to one relationship.
	 * @param type $field_name
	 * @param type $related_table
	 * @param type $display_field
	 */
	public function set_relation_one_one($field_name, $related_table, $display_field)
	{
		$this->relations[strtolower($field_name)] = array('type' => '1-1', 'related_table' => $related_table, 'display_field' => $display_field);
	}

	/**
	 * Sets a one to many relationship.
	 * @param type $field_name
	 * @param type $related_table
	 * @param type $display_field
	 * @param type $where
	 */
	public function set_relation($field_name, $related_table, $display_field, $where = null)
	{
		$field_name = strtolower($field_name);

		$this->relations[$field_name] = array(
				'type' => '1-n',
				'related_table' => $related_table,
				'display_field' => $display_field,
				'where' => $where
			);

		$this->data['display'][$field_name] = $this->get_display_name(str_replace('_id', '', $field_name));
	}

	/**
	 * Sets a many to many relationship.
	 * @param type $field_name
	 * @param type $related_table
	 * @param type $selection_table
	 * @param type $key_this
	 * @param type $key_related
	 * @param type $title_field_selection_table
	 * @param type $priority_field
	 * @param type $where
	 */
	public function set_relation_n_n($field_name, $related_table, $selection_table, $key_this, $key_related, $title_field_selection_table, $priority_field = null, $where = null)
	{
		$field_name = strtolower($field_name);

		$this->relations[$field_name] = array(
			'type' => 'n-n',
			'related_table' => $related_table,
			'selection_table' => $selection_table,
			'key_this' => $key_this,
			'key_related' => $key_related,
			'title_field' => $title_field_selection_table,
			'priority_field' => $priority_field,
			'where' => $where
		);
	}

	/**
	 * Changes the label displayed on the specified field.
	 * @param type $field_name
	 * @param type $display_name
	 */
	public function display_as($field_name, $display_name)
	{
		$this->data['display'][$field_name] = $display_name;
	}

	/**
	 * Creates one to many relationship.
	 * @param type $field_name
	 * @param type $related_table
	 * @param type $foreign_key_in_this_table
	 */
	public function set_children($field_name, $related_table, $foreign_key_in_this_table)
	{
		$this->relations[strtolower($field_name)] = array(
				'type' => 'children',
				'related_table' => $related_table,
				'key' => $foreign_key_in_this_table
			);
	}

	/**
	 * Sets the rule for field validation.
	 * @param type $field_name
	 * @param type $validation_rule
	 */
	public function set_rule($field_name, $validation_rule)
	{
		if (isset($this->fields[$field_name]->rules))
		{
			$this->fields[$field_name]->rules .= '|' . $validation_rule;
		}
		else
		{
			$this->fields[$field_name]->rules = $validation_rule;
		}
	}

	/**
	 * Sets the summary field for the main table. Used for displaying on a list.
	 * @param type $field_name
	 */
	public function set_summary_field($field_name)
	{
		$this->data['summary_field'] = $field_name;
	}

	/**
	 * Sets the parent layout used to render AutoCrud.
	 * @param type $view_name
	 */
	public function set_layout($view_name)
	{
		$this->data['layout'] = $view_name;
	}

	/**
	* Sets per-item view layout.
	*/
	public function set_view($item_view_name)
	{
		$this->item_view = $item_view_name;
	}

	/**
	* Sets per-item print view layout.
	*/
	public function set_print_view($item_view_name)
	{
		$this->print_view = $item_view_name;
	}

	/**
	* Sets page title in table.
	*/
	public function set_title($list, $add = null, $edit = null)
	{
		$this->data['title_list'] = $list;

		if ($add)
			$this->data['title_add'] = $add;

		if ($edit)
			$this->data['title_edit'] = $edit;
	}

	public function unset_columns($columns_to_unset)
	{
		$new_columns = array();

		foreach ($this->data['columns'] as $col)
		{
			if ( ! in_array($col, $columns_to_unset))
				$new_columns[] = $col;
		}

		$this->data['columns'] = $new_columns;
	}

	public function unset_fields($fields_to_unset)
	{
		foreach ($this->data['fields'] as $field)
		{
			if ( ! in_array($field, $fields_to_unset))
				$new_columns[] = $field;
		}

		$this->data['fields'] = $new_columns;
	}

	public function enable_view()
	{
		$this->data['enable_view'] = true;
	}

	public function set_default_value($field_name, $value, $mode = 0)
	{
		// modes: 0 = hidden, 1 = shown but not editable, 2 = editable
		$this->fields[$field_name]->default_value = $value;
		$this->fields[$field_name]->mode = $mode;
		$this->fields[$field_name]->type = 'hidden';
		$this->default_values[$field_name] = $value;
	}

	public function callback_column($column_name, $func)
	{
		$this->callback_column[$column_name] = $func;
	}

	public function callback_add($field_name, $func)
	{
		$this->callback_add[$column_name] = $func;
	}

	public function callback_edit($field_name, $func)
	{
		$this->callback_edit[$column_name] = $func;
	}

	public function callback_field($field_name, $func)
	{
		$this->callback_add($field_name, $func);
		$this->callback_edit($field_name, $func);
	}

	public function listen($event, $func)
	{
		$this->listeners[$event] = $func;
	}

	public function fire($event, $args = null)
	{
		if (isset($this->listeners[$event]))
			$this->listeners[$event]($args);
	}

	public function callback_input($field_name, $func)
	{
		$this->callback_input[$field_name] = $func;
	}

	public function notify_listen()
	{
		$this->enable_notifications = true;
	}

	public function notify($mode, $summary)
	{
		if (Auth::user()->email != 'demo@simful.com')
			IoC::resolve('agent_db')
				->table('activities')
				->insert(
					array(
						'user_id' => Auth::user()->id,
						'description' => __('autocrud::notification.' . $mode, array(
								'nama' => Auth::user()->full_name,
								'subject' => $this->data['subject'],
								'summary' => $summary
							))
					));
		else
			IoC::resolve('agent_db')
				->table('activities')
				->insert(
					array(
						'description' => __('autocrud::notification.' . $mode, array(
								'nama' => Auth::user()->full_name,
								'subject' => $this->data['subject'],
								'summary' => $summary
							)) . ' Address: ' . Request::ip() 
					));
	}

	public function set_access(array $access)
	{
		if ( ! in_array('insert', $access))
			$this->can_insert = false;
		if ( ! in_array('edit', $access))
			$this->can_edit = false;
		if ( ! in_array('delete', $access))
			$this->can_delete = false;
	}

	public function set_icon($url)
	{
		$this->data['icon'] = $url;
	}

	public function base_url()
	{
		$base = '';
		for ($i = 1; $i <= $this->base_segment; $i++)
			$base .= '/' . URI::segment($i);

		return $base;
	}

	public function currency_modifier($field_name, $modifier_field)
	{

	}

	public function callback_view($func)
	{
		$this->callback_view = $func;
	}

	public function set_view_data($data)
	{
		$this->data['view_data'] = $data;
	}

	public function rc($field, $errors)
	{
		return Form::control_group($this->data['inputs'][$field][0], $this->data['inputs'][$field][1], $errors->has($field) ? 'error' : '', Form::block_help($errors->first($field)));
	}
}