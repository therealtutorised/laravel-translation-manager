<form class="form-import pull-left" method="POST" action="{{ route('translation-manager.import') }}" role="form" style="margin-right: 20px">
	@if(sizeof($groups) == 0)
	<a href="#">
		<button type="submit" class="btn btn-primary" title="{{ trans('translation-manager::panel.actions.import') }}"><i class="glyphicon glyphicon-download"></i></button>
	</a>
	@else
	<div class="btn-group">
		<button type="button" class="btn btn-primary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="{{ trans('translation-manager::panel.actions.import') }}">
			<i class="glyphicon glyphicon-download"></i>
		</button>
		<ul class="dropdown-menu">
			<li><a href="#" data-option="0">{{ trans('translation-manager::panel.actions.append') }}</a></li>
			<li><a href="#" data-option="1">{{ trans('translation-manager::panel.actions.replace') }}</a></li>
		</ul>
	</div>
	@endif
</form>
<form class="form-export pull-left" method="POST" action="{{ route('translation-manager.publish', '*') }}" role="form" style="margin-right: 20px">
	{{ csrf_field() }}
	<button type="submit" class="btn btn-primary" title="{{ trans('translation-manager::panel.actions.export') }}"><i class="glyphicon glyphicon-upload"></i></button>
</form>
<div class="clearfix"></div>