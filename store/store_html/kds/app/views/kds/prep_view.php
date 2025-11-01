<header class="prep-header">
    <h1 class="prep-title" data-i18n-key="prep_title">物料制备与开封</h1>
    <a href="index.php" class="btn-back"><i class="bi bi-arrow-left"></i> <span data-i18n-key="btn_back_kds">返回KDS</span></a>
</header>

<main class="prep-container">
    
    <div class="prep-sidebar">
        <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">
            <button class="nav-link prep-nav-link active" id="v-pills-packaged-tab" data-bs-toggle="pill" data-bs-target="#v-pills-packaged" type="button" role="tab" aria-controls="v-pills-packaged" aria-selected="true">
                <i class="bi bi-box-arrow-up"></i>
                <span data-i18n-key="section_packaged">开封物料</span>
            </button>
            <button class="nav-link prep-nav-link" id="v-pills-preps-tab" data-bs-toggle="pill" data-bs-target="#v-pills-preps" type="button" role="tab" aria-controls="v-pills-preps" aria-selected="false">
                <i class="bi bi-check-circle"></i>
                <span data-i18n-key="section_preps">门店现制</span>
            </button>
        </div>
    </div>

    <div class="prep-content">

        <div class="prep-search-bar">
            <span class="search-icon"><i class="bi bi-search"></i></span>
            <input type="search" id="material-search-input" class="form-control" placeholder="搜索物料..." data-i18n-key="placeholder_search">
        </div>

        <div class="tab-content" id="v-pills-tabContent">
            <div class="tab-pane fade show active" id="v-pills-packaged" role="tabpanel" aria-labelledby="v-pills-packaged-tab">
                <div id="packaged-goods-list" class="material-list">
                    </div>
            </div>
            <div class="tab-pane fade" id="v-pills-preps" role="tabpanel" aria-labelledby="v-pills-preps-tab">
                <div id="in-store-preps-list" class="material-list">
                    </div>
            </div>
        </div>
    </div>

</main>