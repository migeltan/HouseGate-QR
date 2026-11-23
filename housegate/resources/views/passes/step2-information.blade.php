{{-- Camera-fallback switch + footer layout for the transfer scan blocks.
     Uses its own class names (reg-switch*, reg-transfer-footer) so it can't
     collide with the old reg-mini-toggle* rules, which can now be deleted. --}}
<style>
    .reg-transfer-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px 16px;
        margin-top: 12px;
    }

    .reg-switch {
        position: relative;
        display: inline-flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
        margin: 0;
        padding: 0;
        cursor: pointer;
        user-select: none;
        -webkit-tap-highlight-color: transparent;
    }

    /* visually hidden, but still focusable / accessible */
    .reg-switch .reg-switch-input {
        position: absolute;
        width: 1px;
        height: 1px;
        margin: -1px;
        padding: 0;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
        opacity: 0;
    }

    .reg-switch .reg-switch-track {
        position: relative;
        display: block;
        box-sizing: border-box;
        flex: 0 0 46px;
        width: 46px;
        min-width: 46px;
        height: 26px;
        margin: 0;
        padding: 0;
        border-radius: 999px;
        background: #cbd5e1;
        box-shadow: inset 0 1px 3px rgba(15, 23, 42, .18);
        transition: background-color .25s ease;
    }

    .reg-switch .reg-switch-thumb {
        position: absolute;
        top: 3px;
        left: 3px;
        right: auto;
        bottom: auto;
        display: flex;
        align-items: center;
        justify-content: center;
        box-sizing: border-box;
        width: 20px;
        height: 20px;
        margin: 0;
        padding: 0;
        border-radius: 50%;
        background: #fff;
        color: #94a3b8;
        box-shadow: 0 2px 4px rgba(15, 23, 42, .25);
        transform: translateX(0);
        transition: transform .25s cubic-bezier(.4, 0, .2, 1), color .25s ease;
    }

    .reg-switch .reg-switch-icon {
        font-size: 10px;
        line-height: 1;
        margin: 0;
        padding: 0;
    }

    .reg-switch .reg-switch-text {
        margin: 0;
        padding: 0;
        font-size: 14px;
        font-weight: 600;
        line-height: 1.3;
        text-align: left;
        color: #475569;
        transition: color .2s ease;
    }

    /* hover */
    .reg-switch:hover .reg-switch-track {
        background: #b6c2d1;
    }

    /* checked */
    .reg-switch .reg-switch-input:checked+.reg-switch-track {
        background: #2563eb;
    }

    .reg-switch .reg-switch-input:checked+.reg-switch-track .reg-switch-thumb {
        transform: translateX(20px);
        color: #2563eb;
    }

    .reg-switch:hover .reg-switch-input:checked+.reg-switch-track {
        background: #1d4ed8;
    }

    .reg-switch .reg-switch-input:checked~.reg-switch-text {
        color: #1e293b;
    }

    /* keyboard focus */
    .reg-switch .reg-switch-input:focus-visible+.reg-switch-track {
        outline: 2px solid #2563eb;
        outline-offset: 3px;
    }

    /* disabled */
    .reg-switch .reg-switch-input:disabled+.reg-switch-track,
    .reg-switch .reg-switch-input:disabled~.reg-switch-text {
        opacity: .5;
        cursor: not-allowed;
    }

    @media (max-width: 480px) {
        .reg-transfer-footer {
            flex-direction: column;
            align-items: stretch;
        }

        .reg-transfer-clear {
            width: 100%;
        }
    }

    @media (prefers-reduced-motion: reduce) {

        .reg-switch .reg-switch-track,
        .reg-switch .reg-switch-thumb,
        .reg-switch .reg-switch-text {
            transition: none;
        }
    }
</style>
{{-- Step 2: Visitor Information --}}
<section class="reg-step-card">
    <div class="reg-step-heading reg-step-heading-split">
        <div class="reg-step-heading-left">
            <i class="fa-solid fa-file-lines step-icon"></i>
            <div>
                <p class="reg-step-label">{{ 'Step 2: Information' }}</p>
                <h2>{{ 'Visitor Information Form' }}</h2>
            </div>
        </div>
        <label class="reg-transfer-toggle">
            <input type="checkbox" id="transferModeToggle" onchange="toggleTransferMode(this.checked)">
            <span class="reg-transfer-toggle-track"><span class="reg-transfer-toggle-thumb"></span></span>
            {{ 'Transferring an existing pass?' }}
        </label>
    </div>

    <div class="reg-form-grid">
        {{-- Transfer Mode: scan the old card first, autofill + lock identity fields --}}
        <div class="reg-field full" id="transferModeBlock" style="display:none;">
            <p class="reg-dup-transfer-label">
                {{ "Scan the visitor's old card to pull their info and transfer their pass here." }}
            </p>

            <div class="reg-dup-transfer-input-row">
                <i class="fa-solid fa-qrcode reg-dup-transfer-input-icon" aria-hidden="true"></i>
                <input type="text" id="transferModeHwInput" class="reg-dup-transfer-input"
                    placeholder="{{ 'Scan old card here' }}" autocomplete="off">
                <span id="transferModeSuccess" class="reg-dup-transfer-success hidden">
                    <i class="fa-solid fa-circle-check"></i> {{ 'Verified' }}
                </span>
            </div>

            <div class="reg-transfer-footer">
                <label class="reg-switch" for="transferModeCameraToggle">

                    <div class="flex w-auto h-auto">
                        <input type="checkbox" id="transferModeCameraToggle" class="reg-switch-input"
                            onchange="toggleTransferModeCameraFallback(this.checked)">
                        <span class="reg-switch-track" aria-hidden="true">
                            <span class="reg-switch-thumb">
                                <i class="fa-solid fa-camera reg-switch-icon"></i>
                            </span>
                        </span>
                        <div class="w-auto h-auto flex items-center justify-center ml-2">
                            <span class="reg-switch-text ">{{ 'No scanner on hand? Use camera instead' }}</span>
                        </div>
                    </div>


                </label>
                <button type="button" class="reg-button reg-dup-transfer-clear reg-transfer-clear hidden"
id="transferModeClearBtn" onclick="clearTransferModeScan()">{{ 'Change card' }}</button>
            </div>

            <div class="reg-dup-transfer-scan hidden" id="transferModeScanArea">
                <video id="transferModeVideo" autoplay playsinline muted class="hidden"></video>
                <span id="transferModePlaceholder" class="reg-dup-transfer-scan-hint">
                    {{ 'Point the camera at the QR on the old card.' }}
                </span>
            </div>

            <p class="reg-dup-transfer-status" id="transferModeStatus"></p>

            <div class="reg-transfer-summary hidden" id="transferModeSummary">
                <div class="reg-transfer-card">
                    <span class="reg-transfer-passno">#<span id="tmPassNo"></span></span>
                    <div class="reg-transfer-who">
                        <strong id="tmHolder"></strong>
                        <span id="tmFrom"></span>
                    </div>
                    <span class="reg-transfer-verified"><i class="fa-solid fa-circle-check"></i> Verified</span>
                </div>
                <dl class="reg-transfer-details hidden" id="tmDetails"></dl>
                <p class="reg-transfer-copied">These details are copied from the old card and can't be edited here.</p>
            </div>
        </div>

        {{-- Manual identity fields (hidden while Transfer Mode is on) --}}
        <div class="reg-identity-fields-wrap" id="manualIdentityFields">
            <p class="reg-subhead">Name</p>
            <div class="reg-field">
                <label class="optional">{{ 'First Name' }}</label>
                <input type="text" name="first_name">
            </div>
            <div class="reg-field">
                <label class="optional">{{ 'Middle Name' }}</label>
                <input type="text" name="middle_name">
            </div>
            <div class="reg-field">
                <label class="optional">{{ 'Last Name' }}</label>
                <input type="text" name="last_name">
            </div>

            <p class="reg-subhead">Personal &amp; contact</p>
            <div class="reg-field">
                <label class="optional">{{ 'Gender / Sex' }}</label>
                <select name="gender">
                    <option value="">{{ 'Select' }}</option>
                    <option value="Male">{{ 'Male' }}</option>
                    <option value="Female">{{ 'Female' }}</option>
                    <option value="PNS">{{ 'Prefer not to say' }}</option>
                </select>
            </div>
            <div class="reg-field">
                <label class="optional">{{ 'Contact No.' }}</label>
                <input type="text" name="contact_no" placeholder="+(63) ...">
            </div>
            <div class="reg-field">
                <label class="optional">{{ 'Email Address' }}</label>
                <input type="email" name="visitor_email" placeholder="{{ 'For check-out / expiry reminders' }}">
            </div>

            <p class="reg-subhead">ID &amp; vehicle</p>
            <div class="reg-field">
                <label class="optional">{{ 'Government ID Type' }}</label>
                <select name="id_type">
                    <option value="">{{ 'Select ID type' }}</option>
                    <option value="Driver's License">{{ "Driver's License" }}</option>
                    <option value="UMID">{{ 'UMID' }}</option>
                    <option value="Passport">{{ 'Passport' }}</option>
                    <option value="SSS ID">{{ 'SSS ID' }}</option>
                    <option value="PhilHealth ID">{{ 'PhilHealth ID' }}</option>
                    <option value="PhilSys (National ID)">{{ 'PhilSys (National ID)' }}</option>
                    <option value="Company ID">{{ 'Company ID' }}</option>
                    <option value="Other">{{ 'Other' }}</option>
                </select>
            </div>
            <div class="reg-field">
                <label class="optional">{{ 'ID Number' }}</label>
                <input type="text" name="id_ref" placeholder="e.g. N01-23-456789">
            </div>
            <div class="reg-field">
                <label class="optional">{{ 'Vehicle' }}</label>
                <input type="text" name="vehicle" placeholder="{{ 'Plate number, optional' }}">
            </div>
        </div>

        {{-- Placeholder shown while the duplicate check request is slow --}}
        <div class="reg-field full hidden" id="dupSkeleton" aria-hidden="true">
            <div class="sk-dup">
                <div class="sk sk-line sk-w-60"></div>
                <div class="sk sk-line sk-w-80"></div>
                <div class="sk sk-line sk-w-40"></div>
            </div>
        </div>

        {{-- Workflow 1: duplicate active-pass warning (filled by runDuplicateCheck) --}}
        <div class="reg-field full hidden" id="dupWarning" role="alert" aria-live="polite">
            <div class="reg-dup" id="dupBox">
                <p class="reg-dup-title" id="dupTitle"></p>
                <dl class="reg-dup-details" id="dupDetails"></dl>
                <p class="reg-dup-note" id="dupNote"></p>
                <button type="button" class="reg-button hidden" id="dupCancelBtn"
                    onclick="closeRegisterModal()">{{ 'Cancel registration' }}</button>
                <label class="reg-dup-confirm hidden" id="dupConfirmWrap">
                    <input type="checkbox" name="confirm_different_person" value="1" id="dupConfirmCheck">
                    {{ 'I have verified this is a different person.' }}
                </label>

                <div class="reg-dup-transfer hidden" id="dupTransferBlock">
                    <p class="reg-dup-transfer-label">
                        {{ "Have the visitor's old card? Scan it to move their existing pass to this building instead." }}
                    </p>

                    <div class="reg-dup-transfer-input-row">
                        <i class="fa-solid fa-qrcode reg-dup-transfer-input-icon" aria-hidden="true"></i>
                        <input type="text" id="transferHwInput" class="reg-dup-transfer-input"
                            placeholder="{{ 'Scan old card here' }}" autocomplete="off">
                        <span id="transferQrSuccess" class="reg-dup-transfer-success hidden">
                            <i class="fa-solid fa-circle-check"></i> {{ 'Verified' }}
                        </span>
                    </div>

                    <div class="reg-transfer-footer">
                        <label class="reg-switch" for="transferCameraToggle">
                            <input type="checkbox" id="transferCameraToggle" class="reg-switch-input"
                                onchange="toggleTransferCameraFallback(this.checked)">
                            <span class="reg-switch-track" aria-hidden="true">
                                <span class="reg-switch-thumb">
                                    <i class="fa-solid fa-camera reg-switch-icon"></i>
                                </span>
                            </span>
                            <span class="reg-switch-text">{{ 'No scanner on hand? Use camera instead' }}</span>
                        </label>
                        <button type="button" class="reg-button reg-dup-transfer-clear reg-transfer-clear hidden"
                            id="transferClearBtn" onclick="clearTransferScan()">{{ 'Clear' }}</button>
                    </div>

                    <div class="reg-dup-transfer-scan hidden" id="dupTransferScanArea">
                        <video id="transferQrVideo" autoplay playsinline muted class="hidden"></video>
                        <span id="transferQrPlaceholder" class="reg-dup-transfer-scan-hint">
                            {{ 'Point the camera at the QR on the old card.' }}
                        </span>
                    </div>

                    <p class="reg-dup-transfer-status" id="transferScanStatus"></p>
                    <input type="hidden" name="transfer_qr_token" id="transferQrTokenInput">
                </div>
            </div>
        </div>
    </div>
</section>
