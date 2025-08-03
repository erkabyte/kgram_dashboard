@extends('frontend::layouts.master')

@section('content')

    <div id="livetvthumbnail-section">
        @include('frontend::components.section.livetvthumbnail',  ['livetvthumbnail' => $responseData['slider']])
    </div>

    <div class="container-fluid padding-right-0">
        <div class="overflow-hidden">
            <div id="more-infinity-section">
                @include('frontend::components.section.livetv_category',  ['moreinfinity' => $responseData['category_data']])
            </div>
        </div>
    </div>
   

@endsection
