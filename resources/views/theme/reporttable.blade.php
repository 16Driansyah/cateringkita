<div class="row mt-5">
    <div class="col-lg-3 col-sm-6">
        <div class="card gradient-1">
            <a href="#" style="text-decoration: none;">
                <div class="card-body">
                    <h3 class="card-title text-white">{{ trans('labels.total_orders') }}</h3>
                    <div class="d-inline-block">
                        <h2 class="text-white">{{(@$total_order == 0 ? 0 : @$total_order)}}</h2>
                    </div>
                    <span class="float-right display-5 opacity-5"  style="color:#fff;"><i class="fa fa-bar-chart"></i></span>
                </div>
            </a>
        </div>
    </div>

    <div class="col-lg-3 col-sm-6">
        <div class="card gradient-2">
            <a href="#" style="text-decoration: none;">
                <div class="card-body">
                    <h3 class="card-title text-white">{{ trans('labels.cancelled_orders') }}</h3>
                    <div class="d-inline-block">
                        <h2 class="text-white">{{(@$canceled_order == 0 ? 0 : @$canceled_order)}}</h2>
                    </div>
                    <span class="float-right display-5 opacity-5"  style="color:#fff;"><i class="fa fa-shopping-cart"></i></span>
                </div>
            </a>
        </div>
    </div>

    <div class="col-lg-3 col-sm-6">
        <div class="card gradient-3">
            <a href="#" style="text-decoration: none;">
                <div class="card-body">
                    <h3 class="card-title text-white">{{ trans('labels.total_earning') }}</h3>
                    <div class="d-inline-block">
                        <h2 class="text-white">{{Auth::user()->currency}}{{(@$order_total == 0 ? 0 : number_format(@$order_total, 3))}}</h2>
                    </div>
                    <span class="float-right display-5 opacity-5"  style="color:#fff;"><i class="fa fa-usd"></i></span>
                </div>
            </a>
        </div>
    </div>

</div>
<table id="example" class="table table-striped table-bordered">
    <thead>
        <tr>
            <th>#</th>
            @if (Auth::user()->type == "1")
            <th>{{ trans('labels.branch_name') }}</th>
            @endif
            <th>{{ trans('labels.username') }}</th>
            <th>{{ trans('labels.order_number') }}</th>
            <th>{{ trans('labels.address') }}</th>
            <th>{{ trans('labels.payment_type') }}</th>
            <th>{{ trans('labels.payment_id') }}</th>
            <th>{{ trans('labels.total_amount') }}</th>
            <th>{{ trans('labels.status') }}</th>
            <th>{{ trans('labels.created_at') }}</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $i = 1;
        foreach ($getorders as $orders) {
        ?>
        <tr id="dataid{{$orders->id}}">
            <td>{{$i}}</td>
            @if (Auth::user()->type == "1")
            <td>{{$orders['branch']->name}}</td>
            @endif
            <td>{{$orders['users']->name}}</td>
            <td>{{$orders->order_number}}</td>
            <td>{{($orders->address == NULL ? 'Pickup' : $orders->address)}}</td>
            <td>
                {{ trans('labels.cash_payment') }}
            </td>
            <td>
                --
            </td>
            <td>{{Auth::user()->currency}}{{number_format($orders->order_total, 3)}}</td>
            <td>
                @if($orders->status == '1')
                    {{ trans('labels.order_received') }}
                @elseif ($orders->status == '2')
                    {{ trans('labels.order_on_the_way') }}
                @elseif ($orders->status == '3')
                    {{ trans('labels.assigned_to_driver') }}
                @elseif ($orders->status == '4')
                    {{ trans('labels.delivered') }}
                @else
                    {{ trans('labels.cancelled') }}
                @endif
            </td>
            <td>{{$orders->created_at}}</td>
        </tr>
        <?php
        $i++;
        }
        ?>
    </tbody>
</table>