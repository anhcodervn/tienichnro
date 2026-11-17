export function handleStudioShortcut(event, actions, readOnly = false) {
    if (event.isComposing || event.target?.closest?.('input,textarea,select,[contenteditable]:not([contenteditable="false"]),[role="textbox"]'))
        return false;
    const key = event.code === 'BracketLeft' ? '[' : event.code === 'BracketRight' ? ']' : event.key.toLowerCase();
    const command = event.ctrlKey || event.metaKey;
    let action;
    let argument;
    if (key === 'escape') action = 'escape';
    else if (key === ' ' && !command && !event.altKey) action = 'pan';
    else if (command && ['+', '=', '-', '0', '1'].includes(key) && !event.altKey) {
        action = key === '0' ? 'fit' : key === '1' ? 'actualSize' : 'zoom';
        argument = key === '-' ? -1 : 1;
    } else if (!command && !event.altKey && ['h', 'z'].includes(key)) {
        action = 'tool';
        argument = key === 'h' ? 'hand' : 'zoom';
    } else if (!readOnly) {
        if (command) {
            if (key === 's' && (!event.altKey || event.shiftKey)) action = event.shiftKey ? 'export' : 'save';
            else if (key === 'z') action = event.altKey ? 'undo' : event.shiftKey ? 'redo' : 'toggleUndo';
            else if (!event.altKey && key === 'j') action = 'duplicate';
            else if (!event.altKey && key === 'd') action = 'deselect';
            else if (!event.altKey && key === 'a') action = 'selectAll';
            else if (!event.altKey && key === 't') action = 'transform';
            else if (!event.altKey && ['[', ']'].includes(key)) {
                action = 'reorder';
                argument = (key === '[' ? -1 : 1) * (event.shiftKey ? 100 : 1);
            }
        } else if (!event.altKey) {
            if (key === 'v' || key === 't') {
                action = 'tool';
                argument = key === 'v' ? 'select' : 'text';
            } else if (key === 'u') {
                action = 'shape';
                argument = event.shiftKey;
            } else if (key === 'delete' || key === 'backspace') action = 'remove';
            else if (key.startsWith('arrow')) {
                const directions = { arrowleft: [-1, 0], arrowright: [1, 0], arrowup: [0, -1], arrowdown: [0, 1] };
                if (directions[key]) {
                    action = 'move';
                    argument = directions[key].map((value) => value * (event.shiftKey ? 10 : 1));
                }
            } else if (key === 'enter') action = 'finishTransform';
        }
    }
    if (!action || !actions[action]) return false;
    event.preventDefault();
    if (!event.repeat || ['move', 'zoom', 'pan', 'reorder'].includes(action)) actions[action](argument);
    return true;
}
