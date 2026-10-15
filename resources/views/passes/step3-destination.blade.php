{{-- Step 3: Destination + buildings + pass duration
     Expects $buildings from the parent view (@include inherits it). --}}
<section class="reg-step-card reg-step-card-emphasis">
    <div class="reg-step-heading">
        <i class="fa-solid fa-signs-post step-icon"></i>
        <div>
            <p class="reg-step-label">Step 3: Reason and Other Details</p>
            <h2>Destination</h2>
        </div>
    </div>

    <div class="reg-form-grid">
        <div class="reg-field full reg-transfer-destination-note hidden" id="transferDestinationNote">
            <span class="reg-transfer-summary-label">Transferring</span>
            <span id="transferDestinationNoteText"></span> — choose where they're headed now.
        </div>

        <div class="reg-field full">
            <label>Destination Building(s) <span class="reg-required">*</span> <span class="optional">(select 1 for a
                    single-building pass, or 2+ for a Gate 6 general pass)</span></label>
            <div class="reg-building-grid" id="regBuildingGrid">
                @foreach ($buildings as $b)
                    <label class="reg-building-option">
                        <input type="checkbox" name="building_ids[]" value="{{ $b->id }}"
                            onchange="updateBuildingSelection()">
                        <span class="reg-building-dot" style="background:{{ $b->color_hex }}"></span>
                        <span class="reg-building-name">{{ $b->name }}</span>
                    </label>
                @endforeach
            </div>
            <p class="reg-north-gate-hint" id="northGateHint">
                2 or more buildings selected — this will be issued as a <strong>Gate 6</strong> general pass, valid
                at all selected buildings.
            </p>
        </div>

        {{-- Manual card pick: single building only. Unticked = auto-assign (lowest in-stock number), as before. --}}
        <div class="reg-field full hidden" id="manualCardField">
            <div class="reg-card-row">
                <div class="reg-card-row-text">
                    <span class="reg-card-row-label">Card</span>
                    <span class="reg-card-row-value" id="manualCardSummary">Auto-assign (lowest available number)</span>
                </div>
                <button type="button" class="reg-card-row-btn" id="manualCardOpenBtn" onclick="toggleManualCard(true)">Choose a specific card</button>
            </div>
            <input type="checkbox" id="manualCardToggle" class="hidden" tabindex="-1" aria-hidden="true">
            <div class="reg-card-picker hidden" id="manualCardPicker">
                <div class="reg-card-picker-head">
                    <input type="text" id="manualCardFilter" inputmode="numeric" autocomplete="off" placeholder="Find a number, e.g. 16" oninput="filterManualCards()">
                    <button type="button" class="reg-card-row-btn is-quiet" onclick="toggleManualCard(false)">Use auto-assign instead</button>
                </div>
                <div class="reg-card-grid" id="manualCardGrid" aria-label="Cards in stock"></div>
                <p class="reg-field-note" id="manualCardNote"></p>
            </div>
            <select name="pass_id" id="manualCardSelect" class="hidden" disabled></select>
        </div>

        <div class="reg-field full reg-pair-row">
            <label>Congressman(s) to Visit <span class="reg-required">*</span> <span class="optional">(only congressmen
                    from the selected building(s) are listed)</span></label>
            <label class="optional">Or: Other <span class="optional">(not visiting a congressman)</span></label>

            <div id="congressmanField">
                <div class="cong-picker" id="congPicker">
                    <div class="cong-box" id="congBox">
                        <div class="cong-chips" id="congChips"></div>
                        <input type="text" id="congSearch" autocomplete="off" disabled
                            placeholder="Select a building first…">
                    </div>
                    <div class="cong-list" id="congList"></div>
                </div>
                <div id="congHiddenInputs"></div>
            </div>
            <input type="text" name="office_other" id="officeOther" maxlength="255" required
                placeholder="e.g. HR Office, Secretariat">
            <p class="reg-field-note">Select at least one congressman, or fill in “Other”.</p>
        </div>

        <div class="reg-field half">
            <label>Reason for Visiting <span class="reg-required">*</span></label>
            <select name="purpose_choice" id="purposeChoice" required
                onchange="document.getElementById('purposeOtherInput').classList.toggle('hidden', this.value !== 'Others'); document.getElementById('purposeOtherInput').required = (this.value === 'Others');">
                <option value="">Select reason</option>
                <option value="Official Business">Official Business</option>
                <option value="Financial/Medical Assistance">Financial/Medical Assistance</option>
                <option value="Visit">Visit</option>
                <option value="Others">Others</option>
            </select>
            <input type="text" name="purpose_other" id="purposeOtherInput" class="hidden" style="margin-top:8px;"
                placeholder="Please specify">
        </div>

        <div class="reg-field half">
            <label class="optional">Contact Person/Sponsor</label>
            <input type="text" name="contact_person" maxlength="255" placeholder="e.g. Maria Santos, Chief of Staff">
        </div>

        <div class="reg-field full">
            <label>Registered by</label>
            <input type="text" value="{{ auth()->user()->name }}" readonly tabindex="-1" style="background:#f1f5f9; cursor:not-allowed;">
        </div>

        <div class="reg-field full">
            <label>Pass duration <span class="reg-required">*</span></label>
            <div class="reg-seg">
                <label class="pass-type-option" style="flex:1;">
                    <input type="radio" name="pass_class" value="day" checked onchange="setPassClass('day')"
                        class="sr-only">
                    <span class="pass-type-btn is-active" id="passClassBtnDay">Day</span>
                </label>
                <label class="pass-type-option" style="flex:1;">
                    <input type="radio" name="pass_class" value="long_term" onchange="setPassClass('long_term')"
                        class="sr-only">
                    <span class="pass-type-btn" id="passClassBtnLongTerm">Long-term</span>
                </label>
            </div>
        </div>

        <div class="reg-field full hidden" id="expectedReturnField">
            <label>Expected return date <span class="reg-required">*</span></label>
            <input type="date" name="expected_return_date" id="expectedReturnInput">
            <p class="optional" style="margin-top:6px; font-size:12.5px;" id="expectedReturnCapHint"></p>
        </div>
    </div>
</section>
