{{-- Step 1: Photo capture — visitor face + ID, both styled to match the mockup's camera panel --}}
<section class="reg-step-card">
    <div class="reg-step-heading">
        <i class="fa-solid fa-camera step-icon"></i>
        <div>
            <p class="reg-step-label">Step 1: Camera Terminal</p>
            <h2>Entrance Photo Capture</h2>
        </div>
    </div>

    <div class="reg-camera-grid">
        <div class="reg-camera-col">
            <p class="reg-camera-help">Capture the photo of the visitor properly.</p>
            <div class="reg-camera-preview" id="photoCaptureArea">
                <span class="reg-focus-corner tl"></span>
                <span class="reg-focus-corner tr"></span>
                <span class="reg-focus-corner bl"></span>
                <span class="reg-focus-corner br"></span>
                <video id="photoVideo" autoplay playsinline class="hidden"></video>
                <img id="photoPreview" class="hidden" alt="Captured photo">
                <span id="photoPlaceholderText">Awaiting Camera Feed</span>
            </div>
            <div class="reg-camera-actions">
                <button type="button" id="startCameraBtn" class="reg-button reg-button-blue" onclick="startCamera()">
                    <span aria-hidden="true">&#9654;</span> Start Camera
                </button>
                <button type="button" id="captureBtn" class="reg-button reg-button-blue hidden"
                    onclick="capturePhoto()">Capture</button>
                <button type="button" id="retakeBtn" class="reg-button hidden" onclick="retakePhoto()">Retake</button>
            </div>
        </div>

        <div class="reg-camera-col">
            <p class="reg-camera-help">Capture front and back of the visitor's ID.</p>
            <div class="reg-camera-preview" id="idPhotoCaptureArea">
                <span class="reg-focus-corner tl"></span>
                <span class="reg-focus-corner tr"></span>
                <span class="reg-focus-corner bl"></span>
                <span class="reg-focus-corner br"></span>
                <video id="idPhotoVideo" autoplay playsinline class="hidden"></video>
                <img id="idPhotoPreview" class="hidden" alt="Captured ID">
                <span id="idPhotoPlaceholderText">Capture Front and Back of the ID</span>
                <button type="button" id="idArrowLeft" class="hidden" onclick="goToIdSlide(0)" aria-label="View front"
                    style="position:absolute; left:8px; top:50%; transform:translateY(-50%); width:32px; height:32px; border-radius:50%; background:rgba(15,23,42,0.55); color:#fff; border:0; font-size:14px; cursor:pointer; z-index:5;">&#9664;</button>
                <button type="button" id="idArrowRight" class="hidden" onclick="goToIdSlide(1)" aria-label="View back"
                    style="position:absolute; right:8px; top:50%; transform:translateY(-50%); width:32px; height:32px; border-radius:50%; background:rgba(15,23,42,0.55); color:#fff; border:0; font-size:14px; cursor:pointer; z-index:5;">&#9654;</button>
            </div>
            <div class="reg-camera-actions">
                <button type="button" id="idMainBtn" class="reg-button reg-button-blue" onclick="handleIdMainButton()">
                    <span aria-hidden="true">&#9654;</span> Start Camera
                </button>
            </div>
            <p class="reg-camera-help" id="idCaptureStatus" style="font-weight:700; color:#64748b;">Capture the
                visitor's ID — front, then back.</p>
            <input type="hidden" name="id_photo_data" id="idPhotoDataInput">
        </div>
    </div>

    <canvas id="photoCanvas" class="hidden"></canvas>
    <input type="hidden" name="photo_data" id="photoDataInput">
    <canvas id="idPhotoCanvas" class="hidden"></canvas>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tesseract.js/5.1.1/tesseract.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jsqr/1.4.0/jsQR.js"></script>
</section>
