/*
 * @author Stéphane LaFlèche <stephane.l@vanillaforums.com>
 * @copyright 2009-2019 Vanilla Forums Inc.
 * @license GPL-2.0-only
 */

import React, { useEffect, useMemo, useRef, useState } from "react";
import classNames from "classnames";
import { useField } from "formik";
import { t } from "@vanilla/i18n/src";
import { uniqueIDFromPrefix } from "@library/utility/idUtils";
import { themeBuilderClasses } from "@library/forms/themeEditor/themeBuilderStyles";
import { getDefaultOrCustomErrorMessage, isValidColor } from "@library/styles/styleUtils";
import { inputNumberClasses } from "@library/forms/themeEditor/inputNumberStyles";
import Button from "@library/forms/Button";
import { ButtonTypes } from "@library/forms/buttonStyles";

type IErrorWithDefault = string | boolean; // Uses default message if true

export interface IInputNumber {
    inputProps?: Omit<React.HTMLAttributes<HTMLInputElement>, "type" | "id" | "tabIndex" | "step" | "min" | "max">;
    inputID: string;
    variableID: string;
    labelID: string;
    defaultValue?: number;
    placeholder?: number;
    inputClass?: string;
    errors?: IErrorWithDefault[]; // Uses default message if true
    step?: number;
    min?: number;
    max?: number;
    errorMessage?: string;
}

export default function InputNumber(props: IInputNumber) {
    const classes = inputNumberClasses();
    const textInput = useRef<HTMLInputElement>(null);
    const builderClasses = themeBuilderClasses();
    const errorMessage = getDefaultOrCustomErrorMessage(props.errorMessage, t("Invalid Number"));

    const { step = 1, min = 0, max } = props;

    const validatedStep = Number.isInteger(step) ? step : 1;
    const validatedMin = Number.isInteger(min) ? min : 0;
    const validatedMax = max && Number.isInteger(max) ? max : undefined;

    /**
     * Check if is valid number, respecting parameters.
     * @param number
     */
    const isValidValue = (numberVal: number | string) => {
        if (numberVal !== undefined && Number.isInteger(ensureInteger(numberVal))) {
            const validatedNumber = parseInt(numberVal.toString());
            return (
                validatedNumber % validatedStep === 0 &&
                validatedNumber >= min &&
                (!validatedMax ? validatedNumber <= validatedMax! : true)
            );
        }
        return false;
    };

    const errorID = useMemo(() => {
        return uniqueIDFromPrefix("inputNumberError");
    }, []);

    const ensureInteger = (val: number | string) => {
        return parseInt(val.toString());
    };

    const [number, numberMeta, helpers] = useField(props.variableID);
    const [errorField, errorMeta, errorHelpers] = useField("errors." + props.variableID);

    // Check initial value for errors
    useEffect(() => {
        if (number.value === undefined) {
            if (props.defaultValue !== undefined && Number.isInteger(ensureInteger(props.defaultValue))) {
                helpers.setValue(props.defaultValue);
            } else {
                helpers.setValue("");
            }
        }
    }, []);

    const onTextChange = e => {
        helpers.setTouched(true);
        let newVal = e.target.value;
        if (Number.isInteger(newVal)) {
            newVal = ensureInteger(newVal);
            helpers.setValue(newVal);
            errorHelpers.setValue(false);
        } else {
            e.preventDefault();
        }
    };

    const stepUp = () => {
        let newValue = ensureInteger(number.value || 0) + validatedStep;
        if (validatedMax !== undefined && newValue > validatedMax) {
            newValue = validatedMax;
        }
        helpers.setValue(newValue);
    };

    const stepDown = () => {
        let newValue = ensureInteger(number.value || 0) - validatedStep;
        if (validatedMin !== undefined && newValue < validatedMin) {
            newValue = validatedMin;
        }
        helpers.setValue(newValue.toString());
    };

    const hasError = number.value ? !!errorField.value || (!isValidValue(number.value) && number.value === "") : false;

    console.log("=== hasError ===");
    console.log("number.value: ", number.value);
    console.log("numberMeta.error: ", errorField.value);
    console.log(
        '(!isValidValue(number.value) && number.value === ""): ',
        !isValidValue(number.value) && number.value === "",
    );
    console.log("!isValidValue(number.value): ", !isValidValue(number.value));
    console.log('number.value === "": ', number.value === "");

    console.log("================");

    // Check initial value for errors
    useEffect(() => {
        if (hasError) {
            helpers.setError(true);
        } else {
            helpers.setError(false);
        }
    }, []);

    return (
        <>
            <span className={classes.root}>
                <input
                    ref={textInput}
                    type="number"
                    aria-describedby={props.labelID}
                    aria-hidden={true}
                    className={classNames(classes.textInput, {
                        [builderClasses.invalidField]: hasError,
                    })}
                    placeholder={props.placeholder ? props.placeholder.toString() : ""}
                    value={number.value || ""}
                    onChange={onTextChange}
                    auto-correct="false"
                    step={validatedStep}
                    min={validatedMin}
                    max={validatedMax}
                    // defaultValue={props.defaultValue}
                />
                <span className={classes.spinner}>
                    <span className={classes.spinnerSpacer}>
                        <Button
                            onClick={stepUp}
                            disabled={!max && number.value && number.value >= max!}
                            className={classes.stepUp}
                            baseClass={ButtonTypes.CUSTOM}
                        >
                            +
                        </Button>
                        <Button
                            onClick={stepDown}
                            disabled={number.value === min}
                            className={classes.stepDown}
                            baseClass={ButtonTypes.CUSTOM}
                        >
                            -
                        </Button>
                    </span>
                </span>
            </span>
            {hasError && (
                <ul id={errorID} className={builderClasses.errorContainer}>
                    <li className={builderClasses.error}>{errorMessage}</li>
                </ul>
            )}
        </>
    );
}
