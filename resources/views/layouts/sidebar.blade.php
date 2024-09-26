<div id="sidebar" class="active">
    <div class="sidebar-wrapper active">
        <div class="sidebar-header position-relative">
            <div class="d-block">
                <div class="logo text-center">
                    <a href="{{ url('home') }}">
                        <img src="{{ $company_logo ?? ''}}"
                             data-custom-image="{{url('assets/images/logo/sidebar_logo.png')}}" alt="Logo" srcset="">
                    </a>
                </div>
            </div>
        </div>

        <div class="sidebar-menu">
            <ul class="menu">
                <li class="sidebar-item">
                    <a href="{{ url('home') }}" class='sidebar-link'>
                        <i class="bi  bi-house"></i>
                        <span class="menu-item">{{ __('Dashboard') }}</span>
                    </a>
                </li>
                <div class="sidebar-new-title">Items Management</div>
                @canany(['category-list','category-create','category-update','category-delete','custom-field-list','custom-field-create','custom-field-update','custom-field-delete'])
                    @canany(['category-list','category-create','category-update','category-delete'])
                        <li class="sidebar-item sidebar-submenus">
                            <a href="{{ route('category.index') }}" class='sidebar-link'>
                                <i class="bi bi-list-task"></i>
                                <span class="menu-item">{{ __('Categories') }}</span>
                            </a>
                        </li>
                    @endcanany
                @endcanany

                @canany(['item-list','item-create','item-update','item-delete','tip-list','tip-create','tip-update','tip-delete'])
                    @canany(['item-list','item-create','item-update','item-delete'])
                        <li class="sidebar-item">
                            <a href="{{ url('item') }}" class='sidebar-link'>
                                <i class="bi bi-ui-radios-grid"></i>
                                <span class="menu-item">{{ __('Items') }}</span>
                            </a>
                        </li>
                    @endcanany
                @endcanany
                <div class="sidebar-new-title">Customers & Sales</div>
                @canany(['custom-field-list','custom-field-create','custom-field-update','custom-field-delete','customer-list','customer-create','customer-update','customer-delete'])
                    @canany(['customer-list','customer-create','customer-update','customer-delete'])
                        <li class="sidebar-item">
                            <a href="{{ url('customer') }}" class='sidebar-link'>
                                <i class="bi bi-people"></i>
                                <span class="menu-item">{{ __('Customers') }}</span>
                            </a>
                        </li>
                    @endcanany
                @endcanany

                <li class="sidebar-item">
                    <a href="{{ route('bidcoinpackage.index') }}" class='sidebar-link'>
                        <i class="bi bi-coin"></i>
                        <span class="menu-item">Paket BidCoin</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="{{ route('bidcoinpurchase.index') }}" class='sidebar-link'>
                        <i class="bi bi-receipt"></i>
                        <span class="menu-item">Pembelian BidCoin</span>
                    </a>
                </li>

                <li class="sidebar-item">
                    <a href="{{ route('item.indexsales') }}" class='sidebar-link'>
                        <i class="bi bi-receipt"></i>
                        <span class="menu-item">Pembelian BidCoin</span>
                    </a>
                </li>
                @canany(['role-list','role-create','role-update','role-delete','staff-list','staff-create','staff-update','staff-delete'])
                    <div class="sidebar-new-title">{{ __('Staff Management') }}</div>
                    @canany(['role-list','role-create','role-update','role-delete'])
                        <li class="sidebar-item">
                            <a href="{{ route('roles.index') }}" class='sidebar-link'>
                                <i class="bi bi-person-bounding-box"></i>
                                <span class="menu-item">{{ __('Role') }}</span>
                            </a>
                        </li>
                    @endcanany
                    @canany(['staff-list','staff-create','staff-update','staff-delete'])
                        <li class="sidebar-item">
                            <a href="{{ route('staff.index') }}" class='sidebar-link'>
                                <i class="bi bi-gear"></i>
                                <span class="menu-item">{{ __('Staff Management') }}</span>
                            </a>
                        </li>
                    @endcanany
                @endcanany

                @canany(['slider-list','slider-create','slider-update','slider-delete','feature-section-list','feature-section-create','feature-section-update','feature-section-delete'])
                    <div class="sidebar-new-title">CMS</div>
                    @canany(['slider-list','slider-create','slider-update','slider-delete'])
                        <li class="sidebar-item">
                            <a href="{{ url('slider') }}" class='sidebar-link'>
                                <i class="bi bi-sliders2"></i>
                                <span class="menu-item">{{ __('Slider') }}</span>
                            </a>
                        </li>
                    @endcanany

                    @canany(['feature-section-list','feature-section-create','feature-section-update','feature-section-delete'])
                        <li class="sidebar-item">
                            <a href="{{ route('feature-section.index') }}" class='sidebar-link'>
                                <i class="bi bi-grid-1x2"></i>
                                <span class="menu-item">{{ __('Feature Section') }}</span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="{{ route('faq.index') }}" class='sidebar-link'>
                                <i class="bi bi-question-square-fill"></i>
                                <span class="menu-item">{{ __('FAQs') }}</span>
                            </a>
                        </li>

                    @endcanany
                @endcanany

                
                @canany(['settings-update'])
                    <div class="sidebar-new-title">{{ __('System Settings') }}</div>
                    @can('settings-update')
                        <li class="sidebar-item">
                            <a href="{{ route('settings.index') }}" class='sidebar-link'>
                                <i class="bi bi-gear"></i>
                                <span class="menu-item">{{ __('Settings') }}</span>
                            </a>
                        </li>
                    @endcan
                @endcanany
            </ul>
        </div>
    </div>
</div>
